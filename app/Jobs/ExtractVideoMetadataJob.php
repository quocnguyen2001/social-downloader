<?php

namespace App\Jobs;

use App\Enums\DownloadSessionStatus;
use App\Models\DownloadOption;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\YtDlpService;
use App\Services\VideoExtraction\Exceptions\YtDlpException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to extract video metadata using yt-dlp and create DownloadOption records.
 */
class ExtractVideoMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $downloadSessionId,
        private array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(YtDlpService $ytDlpService): void
    {
        $startTime = microtime(true);

        try {
            $downloadSession = $this->getDownloadSession();

            Log::info('Starting video metadata extraction', [
                'download_session_id' => $this->downloadSessionId,
                'url' => $downloadSession->original_url,
                'platform' => $downloadSession->platform->value,
            ]);

            // Update status to fetching metadata
            $downloadSession->markAsFetchingMetadata();

            // Get available formats using yt-dlp
            $formats = $ytDlpService->getAvailableFormats($downloadSession->original_url);

            if (empty($formats)) {
                throw new YtDlpException('No video formats found for the provided URL');
            }

            Log::info('Retrieved video formats', [
                'download_session_id' => $this->downloadSessionId,
                'format_count' => count($formats),
            ]);

            // Create DownloadOption records for each format
            $this->createDownloadOptions($downloadSession, $formats);

            // Update session status to metadata fetched
            $downloadSession->markAsMetadataFetched();

            $processingTime = microtime(true) - $startTime;

            Log::info('Video metadata extraction completed', [
                'download_session_id' => $this->downloadSessionId,
                'processing_time' => $processingTime,
                'formats_created' => count($formats),
            ]);

        } catch (YtDlpException $e) {
            $this->handleYtDlpError($e);
        } catch (\Exception $e) {
            $this->handleGeneralError($e);
        }
    }

    /**
     * Handle the job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video metadata extraction job failed permanently', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed('Metadata extraction failed: ' . $exception->getMessage());
        } catch (\Exception $e) {
            Log::error('Failed to update session after job failure', [
                'download_session_id' => $this->downloadSessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the download session.
     */
    private function getDownloadSession(): DownloadSession
    {
        $session = DownloadSession::find($this->downloadSessionId);

        if (!$session) {
            throw new \RuntimeException("Download session not found: {$this->downloadSessionId}");
        }

        return $session;
    }

    /**
     * Create DownloadOption records for each format.
     */
    private function createDownloadOptions(DownloadSession $downloadSession, array $formats): void
    {
        DB::transaction(function () use ($downloadSession, $formats) {
            // Clear any existing download options for this session
            $downloadSession->downloadOptions()->delete();

            foreach ($formats as $format) {
                // Skip formats that are not suitable for download
                if (!$format->isSuitableForDownload()) {
                    continue;
                }

                // Get the base data from format mapping
                $downloadOptionData = $format->toArray();

                // Add required fields
                $downloadOptionData['download_session_id'] = $downloadSession->id;

                // Set estimated download time based on file size
                $downloadOptionData['estimated_download_time'] = $this->estimateDownloadTime($format->filesize);

                DownloadOption::query()->create($downloadOptionData);
            }
        });

        Log::info('Created download options', [
            'download_session_id' => $downloadSession->id,
            'options_created' => $downloadSession->downloadOptions()->count(),
        ]);
    }

    /**
     * Estimate download time based on file size.
     */
    private function estimateDownloadTime(?int $fileSize): ?int
    {
        if (!$fileSize) {
            return null;
        }

        // Assume average download speed of 1 MB/s (conservative estimate)
        $averageSpeedBytesPerSecond = 1024 * 1024; // 1 MB/s

        return (int) ceil($fileSize / $averageSpeedBytesPerSecond);
    }

    /**
     * Handle yt-dlp specific errors.
     */
    private function handleYtDlpError(YtDlpException $e): void
    {
        Log::error('yt-dlp error during metadata extraction', [
            'download_session_id' => $this->downloadSessionId,
            'error' => $e->getMessage(),
            'exit_code' => $e->getExitCode(),
            'is_unsupported_url' => $e->isUnsupportedUrl(),
            'is_network_error' => $e->isNetworkError(),
            'is_video_unavailable' => $e->isVideoUnavailable(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed($e->getUserFriendlyMessage());
        } catch (\Exception $updateException) {
            Log::error('Failed to update session after yt-dlp error', [
                'download_session_id' => $this->downloadSessionId,
                'update_error' => $updateException->getMessage(),
            ]);
        }

        // Don't retry for certain types of errors
        if ($e->isUnsupportedUrl() || $e->isVideoUnavailable()) {
            $this->fail($e);
        } else {
            throw $e; // Allow retry for network errors
        }
    }

    /**
     * Handle general errors.
     */
    private function handleGeneralError(\Exception $e): void
    {
        Log::error('General error during metadata extraction', [
            'download_session_id' => $this->downloadSessionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed('Extraction failed: ' . $e->getMessage());
        } catch (\Exception $updateException) {
            Log::error('Failed to update session after general error', [
                'download_session_id' => $this->downloadSessionId,
                'update_error' => $updateException->getMessage(),
            ]);
        }

        throw $e; // Allow retry
    }
}
