<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DownloadOption;
use App\Services\FileUploadService;
use App\Services\VideoExtraction\YtDlpService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing video download requests.
 *
 * This job handles the complete video download workflow:
 * 1. Downloads video using yt-dlp
 * 2. Uploads to cloud storage
 * 3. Updates download option status
 */
class ProcessVideoDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 1800; // 30 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     *
     * @param  string  $downloadOptionId  The download option ID to process
     */
    public function __construct(
        private string $downloadOptionId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(YtDlpService $ytDlpService, FileUploadService $fileUploadService): void
    {
        $startTime = microtime(true);

        Log::info('Starting video download job', [
            'download_option_id' => $this->downloadOptionId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $downloadOption = $this->getDownloadOption();

            // Mark as processing
            $downloadOption->markAsProcessing();

            // Get the download session to extract origin_url
            $downloadSession = $downloadOption->downloadSession;
            if (! $downloadSession) {
                throw new Exception('Download session not found for option: '.$this->downloadOptionId);
            }

            $originUrl = $downloadSession->original_url;
            $cdnId = $downloadOption->cdn_id;

            if (! $cdnId) {
                throw new Exception('CDN ID not found for download option: '.$this->downloadOptionId);
            }

            Log::info('Processing video download', [
                'download_option_id' => $this->downloadOptionId,
                'origin_url' => $originUrl,
                'cdn_id' => $cdnId,
            ]);

            $downloadSessionId = $downloadOption->quality === 'audio' ? null : $downloadOption->download_session_id;

            // Download video using yt-dlp
            $downloadResult = $ytDlpService->downloadVideo($originUrl, $cdnId, downloadSessionId: $downloadSessionId);

            Log::info('Video downloaded successfully', [
                'download_option_id' => $this->downloadOptionId,
                'file_path' => $downloadResult['file_path'],
                'file_size' => $downloadResult['file_size'],
                'title' => $downloadResult['title'],
            ]);

            // Upload to cloud storage
            $uploadResult = $fileUploadService->uploadFile(
                $downloadResult['file_path'],
                basename($downloadResult['file_path']),
                config('video-extraction.upload.default_storage_disk'),
                config('video-extraction.upload.cleanup_local_after_upload', true)
            );

            Log::info('Video uploaded to cloud storage', [
                'download_option_id' => $this->downloadOptionId,
                'storage_disk' => $uploadResult['storage_disk'],
                'storage_file_path' => $uploadResult['storage_file_path'],
                'file_size' => $uploadResult['file_size'],
            ]);

            // Update download option with storage information
            $downloadOption->markAsDownloaded(
                $uploadResult['storage_disk'],
                $uploadResult['storage_file_path'],
                $uploadResult['file_size']
            );

            $processingTime = microtime(true) - $startTime;

            Log::info('Video download job completed successfully', [
                'download_option_id' => $this->downloadOptionId,
                'processing_time' => $processingTime,
                'storage_disk' => $uploadResult['storage_disk'],
                'storage_file_path' => $uploadResult['storage_file_path'],
            ]);

        } catch (Exception $e) {
            $this->handleJobFailure($e, $startTime);
        }
    }

    /**
     * Handle job failure.
     */
    private function handleJobFailure(Exception $e, float $startTime): void
    {
        $processingTime = microtime(true) - $startTime;

        Log::error('Video download job failed', [
            'download_option_id' => $this->downloadOptionId,
            'attempt' => $this->attempts(),
            'processing_time' => $processingTime,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        try {
            $downloadOption = $this->getDownloadOption();

            // Mark as failed if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $downloadOption->markAsFailed();

                Log::error('Video download job permanently failed', [
                    'download_option_id' => $this->downloadOptionId,
                    'total_attempts' => $this->attempts(),
                ]);
            }
        } catch (Exception $updateException) {
            Log::error('Failed to update download option status after job failure', [
                'download_option_id' => $this->downloadOptionId,
                'original_error' => $e->getMessage(),
                'update_error' => $updateException->getMessage(),
            ]);
        }

        // Re-throw the exception to trigger job retry mechanism
        throw $e;
    }

    /**
     * Get the download option model.
     */
    private function getDownloadOption(): DownloadOption
    {
        $downloadOption = DownloadOption::with('downloadSession')->find($this->downloadOptionId);

        if (! $downloadOption) {
            throw new Exception('Download option not found: '.$this->downloadOptionId);
        }

        return $downloadOption;
    }

    /**
     * The job failed to process.
     */
    public function failed(Exception $exception): void
    {
        Log::error('Video download job failed permanently', [
            'download_option_id' => $this->downloadOptionId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        try {
            $downloadOption = $this->getDownloadOption();
            $downloadOption->markAsFailed();
        } catch (Exception $e) {
            Log::error('Failed to mark download option as failed', [
                'download_option_id' => $this->downloadOptionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
