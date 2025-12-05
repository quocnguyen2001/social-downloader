<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DownloadOptionStatus;
use App\Enums\HttpMethod;
use App\Enums\VideoQuality;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckDownloadStatusRequest;
use App\Http\Requests\Api\TriggerVideoDownloadRequest;
use App\Jobs\ProcessVideoDownload;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\DownloadOption;
use App\Services\AuthenticatedApiKey;
use App\Services\DownloadUrlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller for video download API endpoints.
 */
class VideoDownloadController extends Controller
{
    /**
     * Constructor
     */
    public function __construct(
        private readonly DownloadUrlService $downloadUrlService
    ) {}

    /**
     * Trigger video download for a specific download option.
     */
    public function triggerDownload(TriggerVideoDownloadRequest $request): JsonResponse
    {
        $apiKey = AuthenticatedApiKey::get();

        try {
            $downloadOptionId = $request->validated('download_option_id');

            // Find the download option
            $downloadOption = DownloadOption::with('downloadSession')->find($downloadOptionId);

            if (! $downloadOption) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.not_found', [
                        'resource' => __('models.download_option.singular'),
                    ]),
                ], 404);
            }

            // Check if download option is in a valid state for processing
            if ($downloadOption->status === DownloadOptionStatus::PROCESSING) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.already_processing'),
                ], 409, $downloadOption);
            }

            if ($downloadOption->status === DownloadOptionStatus::DOWNLOADED) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.already_downloaded'),
                    'data' => [
                        'download_url' => $downloadOption->getDownloadUrl(),
                    ],
                ], 409, $downloadOption);
            }

            // Validate that the download option has required data
            if (! $downloadOption->cdn_id) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.missing_cdn_id'),
                ], 400, $downloadOption);
            }

            if (! $downloadOption->downloadSession) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.missing_download_session'),
                ], 400, $downloadOption);
            }

            if (! $downloadOption->downloadSession->original_url) {
                return $this->respondWithDownloadLog($request, $apiKey, [
                    'success' => false,
                    'message' => __('messages.error.missing_origin_url'),
                ], 400, $downloadOption);
            }

            Log::info('Triggering video download', [
                'download_option_id' => $downloadOptionId,
                'cdn_id' => $downloadOption->cdn_id,
                'origin_url' => $downloadOption->downloadSession->original_url,
            ]);

            // Dispatch the background job
            ProcessVideoDownload::dispatch($downloadOptionId);

            return $this->respondWithDownloadLog($request, $apiKey, [
                'success' => true,
                'message' => __('messages.success.download_triggered'),
                'data' => [
                    'download_option_id' => $downloadOptionId,
                    'status' => DownloadOptionStatus::PROCESSING->value,
                ],
            ], 200, $downloadOption);

        } catch (\Exception $e) {
            Log::error('Failed to trigger video download', [
                'download_option_id' => $request->input('download_option_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->respondWithDownloadLog($request, $apiKey, [
                'success' => false,
                'message' => __('messages.error.internal_server_error'),
            ], 500);
        }
    }

    /**
     * Check download status for a specific download option.
     */
    public function checkStatus(CheckDownloadStatusRequest $request): JsonResponse
    {
        try {
            $downloadOptionId = $request->validated('download_option_id');

            // Find the download option
            $downloadOption = DownloadOption::find($downloadOptionId);

            if (! $downloadOption) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.not_found', [
                        'resource' => __('models.download_option.singular'),
                    ]),
                ], 404);
            }

            $responseData = [
                'download_option_id' => $downloadOptionId,
                'status' => $downloadOption->status->value,
                'status_label' => $downloadOption->status->getLabel(),
            ];

            // Add download URL if status is downloaded
            if ($downloadOption->status === DownloadOptionStatus::DOWNLOADED) {
                $downloadUrl = $downloadOption->getDownloadUrl();

                if ($downloadUrl) {
                    $responseData['download_url'] = $downloadUrl;
                    $responseData['file_size'] = $downloadOption->file_size;
                    $responseData['storage_disk'] = $downloadOption->storage_disk;
                }

                return response()->json([
                    'success' => true,
                    'message' => __('messages.success.download_ready'),
                    'data' => $responseData,
                ]);
            }

            // Handle processing status
            if ($downloadOption->status === DownloadOptionStatus::PROCESSING) {
                return response()->json([
                    'success' => true,
                    'message' => __('messages.info.download_processing'),
                    'data' => $responseData,
                ]);
            }

            // Handle failed status
            if ($downloadOption->status === DownloadOptionStatus::FAILED) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.download_failed'),
                    'data' => $responseData,
                ], 422);
            }

            // Handle CDN status (not yet processed)
            return response()->json([
                'success' => true,
                'message' => __('messages.info.download_not_started'),
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to check download status', [
                'download_option_id' => $request->input('download_option_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.error.internal_server_error'),
            ], 500);
        }
    }

    /**
     * Download file for a specific download option.
     * Public endpoint that serves files directly when all conditions are met.
     */
    public function downloadFile(string $downloadOptionId)
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        try {
            // Validate UUID format
            if (! Str::isUuid($downloadOptionId)) {
                return response()->json([
                    'success' => false,
                    'message' => __('validation.uuid', [
                        'attribute' => __('models.download_option.fields.id'),
                    ]),
                ], 400);
            }

            // Find the download option
            $downloadOption = DownloadOption::find($downloadOptionId);

            if (! $downloadOption) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.not_found', [
                        'resource' => __('models.download_option.singular'),
                    ]),
                ], 404);
            }

            // Check business logic conditions:
            // 1. storage_disk must be present and not null
            // 2. storage_file_path must be present and not null
            // 3. status must equal exactly "downloaded"
            if (! $downloadOption->storage_disk ||
                ! $downloadOption->storage_file_path ||
                $downloadOption->status !== DownloadOptionStatus::DOWNLOADED) {

                return response()->json([
                    'success' => false,
                    'message' => __('errors.download_not_available'),
                ], 422);
            }

            // Check if file exists on storage disk
            if (! Storage::disk($downloadOption->storage_disk)->exists($downloadOption->storage_file_path)) {
                return response()->json([
                    'success' => false,
                    'message' => __('errors.file_not_found'),
                ], 404);
            }

            // Return file download using DownloadUrlService
            return $this->downloadUrlService->getDownloadResponse($downloadOption);

        } catch (\Exception $e) {
            Log::error('File download failed', [
                'download_option_id' => $downloadOptionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('messages.error.internal_server_error'),
            ], 500);
        }
    }

    private function respondWithDownloadLog(
        TriggerVideoDownloadRequest $request,
        ?ApiKey $apiKey,
        array $payload,
        int $statusCode,
        ?DownloadOption $downloadOption = null
    ): JsonResponse {
        $this->logDownloadTriggerRequest($request, $apiKey, $statusCode, $downloadOption);

        return response()->json($payload, $statusCode);
    }

    private function logDownloadTriggerRequest(
        TriggerVideoDownloadRequest $request,
        ?ApiKey $apiKey,
        int $statusCode,
        ?DownloadOption $downloadOption = null
    ): void {
        if (! $apiKey) {
            return;
        }

        $downloadSession = $downloadOption?->downloadSession;
        $requestedQuality = $this->resolveQualityValue($downloadOption?->quality);

        ApiRequest::create([
            'api_key_id' => $apiKey->id,
            'user_id' => $apiKey->user_id,
            'endpoint' => '/api/v1/download/trigger',
            'method' => HttpMethod::POST,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_url' => $downloadSession?->original_url,
            'platform' => $downloadSession?->platform,
            'video_title' => $downloadSession?->title,
            'requested_quality' => $requestedQuality,
            'requested_format' => null,
            'status_code' => $statusCode,
            'response_time' => null,
            'file_size' => $downloadOption?->file_size,
            'download_url' => $downloadOption?->getDownloadUrl(),
            'cost' => $apiKey->price_per_request ?? 0.0,
            'billed' => false,
        ]);
    }

    private function resolveQualityValue(?string $quality): ?string
    {
        if (! $quality) {
            return null;
        }

        foreach (VideoQuality::cases() as $case) {
            if ($case->value === $quality) {
                return $case->value;
            }
        }

        return null;
    }
}
