<?php

namespace App\Http\Controllers\Api;

use App\Enums\DownloadSessionStatus;
use App\Enums\HttpMethod;
use App\Enums\Platform;
use App\Events\VideoExtractionRequested;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\DownloadOption;
use App\Models\DownloadSession;
use App\Models\User;
use App\Services\AuthenticatedApiKey;
use App\Services\VideoExtraction\PlatformDetector;
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
        private PlatformDetector $platformDetector
    ) {}

    /**
     * Get extraction status.
     */
    public function status(Request $request, string $sessionId): JsonResponse
    {
        try {
            $apiKey = AuthenticatedApiKey::get();
            $downloadSession = DownloadSession::find($sessionId);

            if (! $downloadSession) {
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
                'created_at' => $downloadSession->created_at->toISOString(),
                'updated_at' => $downloadSession->updated_at->toISOString(),
            ];

            // Add video metadata if available
            if ($downloadSession->title) {
                $data['video_info'] = [
                    'title' => $downloadSession->title,
                    'thumbnail_url' => $downloadSession->thumbnail_url,
                    'thumbnail_valid' => $downloadSession->isThumbnailValid(),
                    'duration' => $downloadSession->duration,
                ];
            }

            // Add available download options if metadata has been fetched
            if (in_array($downloadSession->status, [DownloadSessionStatus::METADATA_FETCHED, DownloadSessionStatus::READY_FOR_DOWNLOAD])) {

                $downloadOptions = DownloadOption::query()
                    ->where('download_session_id', $downloadSession->id)
                    ->get();

                $data['download_options'] = $downloadOptions->map(function ($option) {
                    return [
                        'id' => $option->id,
                        'quality' => $option->quality,
                        'mime_type' => $option->mime_type,
                        'file_size' => $option->file_size,
                        'formatted_file_size' => $option->getFormattedFileSizeAttribute(),
                        'estimated_download_time' => $option->estimated_download_time,
                        'formatted_estimated_time' => $option->getFormattedEstimatedTimeAttribute(),
                        'status' => $option->status->value,
                        'cdn_id' => $option->cdn_id,
                    ];
                })->toArray();

                $data['total_options'] = count($data['download_options']);

                // Group options by type for easier frontend handling (based on quality field)
                $data['options_by_type'] = [
                    'full_video' => $downloadOptions->filter(fn ($option) => $option->isFull())->values(),
                    'video_only' => $downloadOptions->filter(fn ($option) => $option->isVideoOnly())->values(),
                    'audio_only' => $downloadOptions->filter(fn ($option) => $option->isAudioOnly())->values(),
                ];
            }

            // Add extraction results if completed (legacy support)
            if ($downloadSession->status === DownloadSessionStatus::READY_FOR_DOWNLOAD) {
                $data['result'] = [
                    'title' => $downloadSession->title,
                    'thumbnail_url' => $downloadSession->thumbnail_url,
                    'thumbnail_valid' => $downloadSession->isThumbnailValid(),
                    'duration' => $downloadSession->duration,
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
     */
    public function platforms(): JsonResponse
    {
        try {
            $platforms = [];

            foreach ($this->platformDetector->getSupportedPlatforms() as $platform) {
                $platforms[] = [
                    'platform' => $platform->value,
                    'name' => $platform->getLabel(),
                    'description' => __('platforms.'.$platform->value.'.description'),
                    'url_patterns' => $this->platformDetector->getUrlPatterns($platform),
                    'supports_metadata_extraction' => true,
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
     * Calculate the cost for an API request based on the API key tier.
     */
    private function calculateRequestCost(ApiKey $apiKey): float
    {
        return $apiKey->price_per_request ?? 0.0500; // Default cost per request
    }

    /**
     * Create API request record for failed requests.
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
            'endpoint' => '/api/v1/extract/status/'.$sessionId,
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
     */
    public function extractGuest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
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

            // Detect platform
            $platform = $this->platformDetector->detectPlatform($url);
            if (! $platform) {
                // Log failed request for unsupported platform
                $this->createFailedGuestApiRequestRecord($request, $url, 'Unsupported platform', 400);

                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            $apiKey = AuthenticatedApiKey::get();

            // Create download session for guest
            $downloadSession = DownloadSession::create([
                'api_key_id' => $apiKey?->getKey(), // No API key for guests
                'user_id' => null, // No user for guests
                'original_url' => $url,
                'platform' => $platform,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Create API request record for tracking
            $apiRequest = $this->createGuestApiRequestRecord($request, $url, $platform);

            // Fire extraction requested event (modified for guest)
            VideoExtractionRequested::dispatch(
                $downloadSession,
                $url,
                $platform,
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
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Extraction request submitted successfully',
                'data' => [
                    'session_id' => $downloadSession->id,
                    'status' => $downloadSession->status->value,
                    'platform' => $platform->value,
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
     */
    public function extractAuthenticated(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'url' => 'required|url|max:2048',
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

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $url = $request->input('url');

            // Detect platform
            $platform = $this->platformDetector->detectPlatform($url);
            if (! $platform) {
                // Log failed request for unsupported platform
                $this->createFailedUserApiRequestRecord($request, $user, $url, 'Unsupported platform', 400);

                return response()->json([
                    'success' => false,
                    'message' => 'Unsupported platform or invalid URL',
                ], 400);
            }

            $apiKey = AuthenticatedApiKey::get();

            // Create download session for authenticated user
            $downloadSession = DownloadSession::create([
                'api_key_id' => $apiKey?->getKey(), // No API key for Sanctum auth
                'user_id' => $user->id,
                'original_url' => $url,
                'platform' => $platform,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            // Create API request record for tracking
            $apiRequest = $this->createUserApiRequestRecord($request, $user, $url, $platform);

            // Fire extraction requested event
            VideoExtractionRequested::dispatch(
                $downloadSession,
                $url,
                $platform,
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
     */
    private function createGuestApiRequestRecord(
        Request $request,
        string $url,
        Platform $platform
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
            'requested_quality' => null,
            'requested_format' => null,
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
     */
    private function createUserApiRequestRecord(
        Request $request,
        User $user,
        string $url,
        Platform $platform
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
            'requested_quality' => null,
            'requested_format' => null,
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
