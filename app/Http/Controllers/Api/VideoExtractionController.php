<?php

namespace App\Http\Controllers\Api;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Events\VideoExtractionRequested;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\DownloadSession;
use App\Services\AuthenticatedApiKey;
use App\Services\VideoExtraction\Factory\DriverFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * API Controller for video extraction functionality.
 *
 * This controller provides endpoints for requesting video extractions
 * and checking the status of extraction jobs.
 */
class VideoExtractionController extends Controller
{
    public function __construct(
        private DriverFactory $driverFactory
    ) {}

    /**
     * Request video extraction.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extract(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
            'quality' => 'sometimes|in:144p,360p,720p,1080p',
            'format' => 'sometimes|in:mp4,webm,mp3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $url = $request->input('url');
            $quality = VideoQuality::from($request->input('quality', '720p'));
            $format = VideoFormat::from($request->input('format', 'mp4'));

            // Detect platform
            $platform = $this->driverFactory->detectPlatform($url);
            if (!$platform) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            // Get API key from singleton (more efficient than request attributes)
            $apiKey = AuthenticatedApiKey::get();

            // Create download session
            $downloadSession = DownloadSession::create([
                'api_key_id' => $apiKey->getKey(),
                'original_url' => $url,
                'platform' => $platform,
                'quality' => $quality,
                'format' => $format,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Fire extraction requested event
            VideoExtractionRequested::dispatch(
                $downloadSession,
                $url,
                $platform,
                $quality,
                $format,
                $apiKey,
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );

            Log::info('Video extraction requested', [
                'download_session_id' => $downloadSession->id,
                'url' => $url,
                'platform' => $platform->value,
                'quality' => $quality->value,
                'format' => $format->value,
                'api_key_id' => $apiKey?->id,
                'api_key_tier' => $apiKey?->tier,
                'request_id' => AuthenticatedApiKey::getRequestId(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Extraction request submitted successfully',
                'data' => [
                    'session_id' => $downloadSession->id,
                    'status' => $downloadSession->status->value,
                    'platform' => $platform->value,
                    'quality' => $quality->value,
                    'format' => $format->value,
                    'estimated_processing_time' => $this->getEstimatedProcessingTime($platform),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Video extraction request failed', [
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process extraction request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get extraction status.
     *
     * @param Request $request
     * @param string $sessionId
     * @return JsonResponse
     */
    public function status(Request $request, string $sessionId): JsonResponse
    {
        try {
            $apiKey = AuthenticatedApiKey::get();
            $downloadSession = DownloadSession::find($sessionId);

            if (!$downloadSession) {
                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            // Check if the session belongs to the authenticated API key
            if ($downloadSession->api_key_id !== $apiKey?->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this session',
                ], 403);
            }

            $data = [
                'session_id' => $downloadSession->id,
                'status' => $downloadSession->status->value,
                'platform' => $downloadSession->platform->value,
                'quality' => $downloadSession->quality->value,
                'format' => $downloadSession->format->value,
                'created_at' => $downloadSession->created_at->toISOString(),
                'updated_at' => $downloadSession->updated_at->toISOString(),
            ];

            // Add extraction results if completed
            if ($downloadSession->status === DownloadSessionStatus::COMPLETED) {
                $data['result'] = [
                    'title' => $downloadSession->title,
                    'thumbnail_url' => $downloadSession->thumbnail_url,
                    'duration' => $downloadSession->duration,
                    'file_size' => $downloadSession->file_size,
                    'download_url' => $downloadSession->download_url,
                    'expires_at' => $downloadSession->expires_at?->toISOString(),
                ];
            }

            // Add error message if failed
            if ($downloadSession->status === DownloadSessionStatus::FAILED) {
                $data['error_message'] = $downloadSession->error_message;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get extraction status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get extraction status',
            ], 500);
        }
    }

    /**
     * Get supported platforms and their capabilities.
     *
     * @return JsonResponse
     */
    public function platforms(): JsonResponse
    {
        try {
            $platforms = [];

            foreach ($this->driverFactory->getSupportedPlatforms() as $platform) {
                $driver = $this->driverFactory->createForPlatform($platform);

                $platforms[] = [
                    'platform' => $platform->value,
                    'name' => $platform->getLabel(),
                    'supported_qualities' => array_map(
                        fn($quality) => $quality->value,
                        $driver->getSupportedQualities()
                    ),
                    'supported_formats' => array_map(
                        fn($format) => $format->value,
                        $driver->getSupportedFormats()
                    ),
                    'url_patterns' => $driver->getUrlPatterns(),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'platforms' => $platforms,
                    'total_platforms' => count($platforms),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get supported platforms', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get supported platforms',
            ], 500);
        }
    }



    /**
     * Get estimated processing time for a platform.
     */
    private function getEstimatedProcessingTime(Platform $platform): int
    {
        return match ($platform) {
            Platform::YOUTUBE => 30,    // 30 seconds
            Platform::TIKTOK => 15,     // 15 seconds
            Platform::INSTAGRAM => 25,  // 25 seconds
            Platform::FACEBOOK => 35,   // 35 seconds
        };
    }
}
