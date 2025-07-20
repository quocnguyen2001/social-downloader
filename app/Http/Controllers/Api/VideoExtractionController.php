<?php

namespace App\Http\Controllers\Api;

use App\Enums\DownloadSessionStatus;
use App\Enums\HttpMethod;
use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Events\VideoExtractionRequested;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\DownloadSession;
use App\Models\User;
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

            // Get API key from singleton (more efficient than request attributes)
            $apiKey = AuthenticatedApiKey::get();

            // Detect platform
            $platform = $this->driverFactory->detectPlatform($url);
            if (!$platform) {
                // Log failed API request for unsupported platform
                $this->createFailedApiRequestRecord($request, $apiKey, $url, 'Unsupported platform', 400);

                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            // Create download session
            $downloadSession = DownloadSession::create([
                'api_key_id' => $apiKey->getKey(),
                'original_url' => $url,
                'platform' => $platform,
                'quality' => $quality,
                'format' => $format,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Create API request record immediately for tracking
            $apiRequest = $this->createApiRequestRecord($request, $apiKey, $url, $platform, $quality, $format);

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
            // Get API key for error logging
            $apiKey = AuthenticatedApiKey::get();

            // Log failed API request for server error
            if ($apiKey) {
                $this->createFailedApiRequestRecord($request, $apiKey, $request->input('url'), $e->getMessage(), 500);
            }

            Log::error('Video extraction request failed', [
                'url' => $request->input('url'),
                'error' => $e->getMessage(),
                'api_key_id' => $apiKey?->id,
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
                // Log failed API request for session not found
                if ($apiKey) {
                    $this->createStatusApiRequestRecord($request, $apiKey, $sessionId, 404);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Session not found',
                ], 404);
            }

            // Check if the session belongs to the authenticated API key
            if ($downloadSession->api_key_id !== $apiKey?->id) {
                // Log failed API request for access denied
                if ($apiKey) {
                    $this->createStatusApiRequestRecord($request, $apiKey, $sessionId, 403);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Access denied to this session',
                ], 403);
            }

            // Log successful API request for status check
            if ($apiKey) {
                $this->createStatusApiRequestRecord($request, $apiKey, $sessionId, 200);
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
            // Get API key for error logging
            $apiKey = AuthenticatedApiKey::get();

            // Log failed API request for server error
            if ($apiKey) {
                $this->createStatusApiRequestRecord($request, $apiKey, $sessionId, 500);
            }

            Log::error('Failed to get extraction status', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
                'api_key_id' => $apiKey?->id,
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

    /**
     * Create API request record for immediate tracking.
     *
     * @param Request $request
     * @param ApiKey $apiKey
     * @param string $url
     * @param Platform $platform
     * @param VideoQuality $quality
     * @param VideoFormat $format
     * @return ApiRequest
     */
    private function createApiRequestRecord(
        Request $request,
        ApiKey $apiKey,
        string $url,
        Platform $platform,
        VideoQuality $quality,
        VideoFormat $format
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => $apiKey->getKey(),
            'user_id' => $apiKey->user_id, // Link to user if API key has user association
            'endpoint' => '/api/v1/extract',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => $platform,
            'video_title' => null, // Will be updated when extraction completes
            'requested_quality' => $quality,
            'requested_format' => $format,
            'status_code' => 200, // Request accepted
            'response_time' => null, // Will be updated when extraction completes
            'file_size' => null, // Will be updated when extraction completes
            'download_url' => null, // Will be updated when extraction completes
            'cost' => $this->calculateRequestCost($apiKey),
            'billed' => false, // Will be processed by billing job
        ]);
    }

    /**
     * Calculate the cost for an API request based on the API key tier.
     *
     * @param ApiKey $apiKey
     * @return float
     */
    private function calculateRequestCost(ApiKey $apiKey): float
    {
        return $apiKey->price_per_request ?? 0.0500; // Default cost per request
    }

    /**
     * Create API request record for failed requests.
     *
     * @param Request $request
     * @param ApiKey $apiKey
     * @param string $url
     * @param string $errorMessage
     * @param int $statusCode
     * @return ApiRequest
     */
    private function createFailedApiRequestRecord(
        Request $request,
        ApiKey $apiKey,
        string $url,
        string $errorMessage,
        int $statusCode
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => $apiKey->getKey(),
            'user_id' => $apiKey->user_id,
            'endpoint' => '/api/v1/extract',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => null, // Unknown platform for failed requests
            'video_title' => null,
            'requested_quality' => null,
            'requested_format' => null,
            'status_code' => $statusCode,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0, // No cost for failed requests
            'billed' => false,
        ]);
    }

    /**
     * Create API request record for status endpoint calls.
     *
     * @param Request $request
     * @param ApiKey $apiKey
     * @param string $sessionId
     * @param int $statusCode
     * @return ApiRequest
     */
    private function createStatusApiRequestRecord(
        Request $request,
        ApiKey $apiKey,
        string $sessionId,
        int $statusCode
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => $apiKey->getKey(),
            'user_id' => $apiKey->user_id,
            'endpoint' => '/api/v1/extract/status/' . $sessionId,
            'method' => HttpMethod::GET,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => null, // No URL for status checks
            'platform' => null, // No platform for status checks
            'video_title' => null,
            'requested_quality' => null,
            'requested_format' => null,
            'status_code' => $statusCode,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0, // No cost for status checks
            'billed' => false,
        ]);
    }

    /**
     * Request video extraction for guest users (unauthenticated).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractGuest(Request $request): JsonResponse
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
            $quality = VideoQuality::from($request->input('quality', '360p')); // Lower default for guests
            $format = VideoFormat::from($request->input('format', 'mp4'));

            // Detect platform
            $platform = $this->driverFactory->detectPlatform($url);
            if (!$platform) {
                // Log failed request for unsupported platform
                $this->createFailedGuestApiRequestRecord($request, $url, 'Unsupported platform', 400);

                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            // Create download session for guest
            $downloadSession = DownloadSession::create([
                'api_key_id' => null, // No API key for guests
                'user_id' => null, // No user for guests
                'original_url' => $url,
                'platform' => $platform,
                'quality' => $quality,
                'format' => $format,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Create API request record for tracking
            $apiRequest = $this->createGuestApiRequestRecord($request, $url, $platform, $quality, $format);

            // Fire extraction requested event (modified for guest)
            VideoExtractionRequested::dispatch(
                $downloadSession,
                $url,
                $platform,
                $quality,
                $format,
                null, // No API key for guests
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'user_type' => 'guest',
                ]
            );

            Log::info('Guest video extraction requested', [
                'download_session_id' => $downloadSession->id,
                'url' => $url,
                'platform' => $platform->value,
                'quality' => $quality->value,
                'format' => $format->value,
                'ip_address' => $request->ip(),
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
                    'user_type' => 'guest',
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Guest video extraction failed', [
                'error' => $e->getMessage(),
                'url' => $request->input('url'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process extraction request',
            ], 500);
        }
    }

    /**
     * Request video extraction for authenticated users.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function extractAuthenticated(Request $request): JsonResponse
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
            /** @var User $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $url = $request->input('url');
            $quality = VideoQuality::from($request->input('quality', '720p')); // Higher default for authenticated users
            $format = VideoFormat::from($request->input('format', 'mp4'));

            // Detect platform
            $platform = $this->driverFactory->detectPlatform($url);
            if (!$platform) {
                // Log failed request for unsupported platform
                $this->createFailedUserApiRequestRecord($request, $user, $url, 'Unsupported platform', 400);

                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            // Create download session for authenticated user
            $downloadSession = DownloadSession::create([
                'api_key_id' => null, // No API key for Sanctum auth
                'user_id' => $user->id,
                'original_url' => $url,
                'platform' => $platform,
                'quality' => $quality,
                'format' => $format,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Create API request record for tracking
            $apiRequest = $this->createUserApiRequestRecord($request, $user, $url, $platform, $quality, $format);

            // Fire extraction requested event
            VideoExtractionRequested::dispatch(
                $downloadSession,
                $url,
                $platform,
                $quality,
                $format,
                null, // No API key for Sanctum auth
                [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'user_type' => 'authenticated',
                    'user_id' => $user->id,
                    'membership_plan' => $user->membershipPlan?->name,
                ]
            );

            Log::info('Authenticated video extraction requested', [
                'download_session_id' => $downloadSession->id,
                'url' => $url,
                'platform' => $platform->value,
                'quality' => $quality->value,
                'format' => $format->value,
                'user_id' => $user->id,
                'membership_plan' => $user->membershipPlan?->name,
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
                    'user_type' => 'authenticated',
                    'membership_plan' => $user->membershipPlan?->name,
                ],
            ]);

        } catch (\Exception $e) {
            $user = $request->user();

            Log::error('Authenticated video extraction failed', [
                'error' => $e->getMessage(),
                'url' => $request->input('url'),
                'user_id' => $user?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process extraction request',
            ], 500);
        }
    }

    /**
     * Create API request record for guest users.
     *
     * @param Request $request
     * @param string $url
     * @param Platform $platform
     * @param VideoQuality $quality
     * @param VideoFormat $format
     * @return ApiRequest
     */
    private function createGuestApiRequestRecord(
        Request $request,
        string $url,
        Platform $platform,
        VideoQuality $quality,
        VideoFormat $format
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => null,
            'user_id' => null,
            'endpoint' => '/api/v1/guest/extract-video',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => $platform,
            'video_title' => null,
            'requested_quality' => $quality,
            'requested_format' => $format,
            'status_code' => 200,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0, // No cost for guest users
            'billed' => false,
        ]);
    }

    /**
     * Create API request record for authenticated users.
     *
     * @param Request $request
     * @param User $user
     * @param string $url
     * @param Platform $platform
     * @param VideoQuality $quality
     * @param VideoFormat $format
     * @return ApiRequest
     */
    private function createUserApiRequestRecord(
        Request $request,
        User $user,
        string $url,
        Platform $platform,
        VideoQuality $quality,
        VideoFormat $format
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => null,
            'user_id' => $user->id,
            'endpoint' => '/api/v1/auth/extract-video',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => $platform,
            'video_title' => null,
            'requested_quality' => $quality,
            'requested_format' => $format,
            'status_code' => 200,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0, // Cost calculation can be added later
            'billed' => false,
        ]);
    }

    /**
     * Create failed API request record for guest users.
     *
     * @param Request $request
     * @param string $url
     * @param string $errorMessage
     * @param int $statusCode
     * @return ApiRequest
     */
    private function createFailedGuestApiRequestRecord(
        Request $request,
        string $url,
        string $errorMessage,
        int $statusCode
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => null,
            'user_id' => null,
            'endpoint' => '/api/v1/guest/extract-video',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => null,
            'video_title' => null,
            'requested_quality' => null,
            'requested_format' => null,
            'status_code' => $statusCode,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0,
            'billed' => false,
        ]);
    }

    /**
     * Create failed API request record for authenticated users.
     *
     * @param Request $request
     * @param User $user
     * @param string $url
     * @param string $errorMessage
     * @param int $statusCode
     * @return ApiRequest
     */
    private function createFailedUserApiRequestRecord(
        Request $request,
        User $user,
        string $url,
        string $errorMessage,
        int $statusCode
    ): ApiRequest {
        return ApiRequest::create([
            'api_key_id' => null,
            'user_id' => $user->id,
            'endpoint' => '/api/v1/auth/extract-video',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $url,
            'platform' => null,
            'video_title' => null,
            'requested_quality' => null,
            'requested_format' => null,
            'status_code' => $statusCode,
            'response_time' => null,
            'file_size' => null,
            'download_url' => null,
            'cost' => 0.0,
            'billed' => false,
        ]);
    }
}
