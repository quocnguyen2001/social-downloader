<?php

namespace App\Jobs;

use App\Enums\DownloadSessionStatus;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\Factory\DriverFactory;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for refreshing video metadata.
 *
 * This job re-extracts metadata for existing download sessions,
 * useful for updating expired download URLs or refreshing data.
 */
class RefreshVideoMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120; // 2 minutes

    /**
     * Create a new job instance.
     *
     * @param  string  $downloadSessionId  The download session ID to refresh
     * @param  bool  $forceRefresh  Whether to force refresh even if not expired
     */
    public function __construct(
        private string $downloadSessionId,
        private bool $forceRefresh = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DriverFactory $driverFactory): void
    {
        Log::info('Starting metadata refresh job', [
            'download_session_id' => $this->downloadSessionId,
            'force_refresh' => $this->forceRefresh,
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Check if refresh is needed
            if (! $this->shouldRefresh($downloadSession)) {
                Log::info('Metadata refresh not needed', [
                    'download_session_id' => $this->downloadSessionId,
                    'expires_at' => $downloadSession->expires_at?->toISOString(),
                ]);

                return;
            }

            // Create driver and extract fresh metadata
            $driver = $driverFactory->create($downloadSession->original_url);

            $extractionResult = $driver->extractMetadata(
                $downloadSession->original_url,
                [
                    'quality' => $downloadSession->quality,
                    'format' => $downloadSession->format,
                ]
            );

            // Update session with fresh data
            $updateData = [
                'title' => $extractionResult->getTitle() ?? $downloadSession->title,
                'thumbnail_url' => $extractionResult->getThumbnail() ?? $downloadSession->thumbnail_url,
                'duration' => $extractionResult->getDuration() ?? $downloadSession->duration,
                'file_size' => $extractionResult->getFileSize() ?? $downloadSession->file_size,
                'download_url' => $extractionResult->getDownloadUrl() ?? $downloadSession->download_url,
                'expires_at' => Carbon::now()->addHours(24),
            ];

            $downloadSession->update(array_filter($updateData, fn ($value) => $value !== null));

            Log::info('Metadata refreshed successfully', [
                'download_session_id' => $this->downloadSessionId,
                'new_expires_at' => $downloadSession->fresh()->expires_at?->toISOString(),
            ]);

        } catch (\Throwable $exception) {
            Log::error('Metadata refresh failed', [
                'download_session_id' => $this->downloadSessionId,
                'exception' => $exception->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $exception;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Metadata refresh job failed permanently', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Mark as expired if refresh failed
            $downloadSession->update([
                'status' => DownloadSessionStatus::FAILED,
                'error_message' => 'Failed to refresh metadata: '.$exception->getMessage(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update session after metadata refresh failure', [
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

        if (! $session) {
            throw new \RuntimeException("Download session not found: {$this->downloadSessionId}");
        }

        return $session;
    }

    /**
     * Check if the session should be refreshed.
     */
    private function shouldRefresh(DownloadSession $session): bool
    {
        // Force refresh if requested
        if ($this->forceRefresh) {
            return true;
        }

        // Don't refresh failed or pending sessions
        if (in_array($session->status, [DownloadSessionStatus::FAILED, DownloadSessionStatus::PENDING])) {
            return false;
        }

        // Refresh if expired or expiring soon (within 1 hour)
        if ($session->expires_at && $session->expires_at->isBefore(Carbon::now()->addHour())) {
            return true;
        }

        // Refresh if no download URL
        if (! $session->download_url) {
            return true;
        }

        return false;
    }
}
