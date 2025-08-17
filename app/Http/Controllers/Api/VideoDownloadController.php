<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DownloadOptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckDownloadStatusRequest;
use App\Http\Requests\Api\TriggerVideoDownloadRequest;
use App\Jobs\ProcessVideoDownload;
use App\Models\DownloadOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Controller for video download API endpoints.
 */
class VideoDownloadController extends Controller
{
    /**
     * Trigger video download for a specific download option.
     */
    public function triggerDownload(TriggerVideoDownloadRequest $request): JsonResponse
    {
        try {
            $downloadOptionId = $request->validated('download_option_id');

            // Find the download option
            $downloadOption = DownloadOption::with('downloadSession')->find($downloadOptionId);

            if (! $downloadOption) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.not_found', [
                        'resource' => __('models.download_option.singular'),
                    ]),
                ], 404);
            }

            // Check if download option is in a valid state for processing
            if ($downloadOption->status === DownloadOptionStatus::PROCESSING) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.already_processing'),
                ], 409);
            }

            if ($downloadOption->status === DownloadOptionStatus::DOWNLOADED) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.already_downloaded'),
                    'data' => [
                        'download_url' => $downloadOption->getDownloadUrl(),
                    ],
                ], 409);
            }

            // Validate that the download option has required data
            if (! $downloadOption->cdn_id) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.missing_cdn_id'),
                ], 400);
            }

            if (! $downloadOption->downloadSession) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.missing_download_session'),
                ], 400);
            }

            if (! $downloadOption->downloadSession->original_url) {
                return response()->json([
                    'success' => false,
                    'message' => __('messages.error.missing_origin_url'),
                ], 400);
            }

            Log::info('Triggering video download', [
                'download_option_id' => $downloadOptionId,
                'cdn_id' => $downloadOption->cdn_id,
                'origin_url' => $downloadOption->downloadSession->original_url,
            ]);

            // Dispatch the background job
            ProcessVideoDownload::dispatch($downloadOptionId);

            return response()->json([
                'success' => true,
                'message' => __('messages.success.download_triggered'),
                'data' => [
                    'download_option_id' => $downloadOptionId,
                    'status' => DownloadOptionStatus::PROCESSING->value,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger video download', [
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

            // Return file download using Laravel's response()->download() method
            return Storage::disk($downloadOption->storage_disk)->download($downloadOption->storage_file_path);

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
}
