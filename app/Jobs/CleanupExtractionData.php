<?php

namespace App\Jobs;

use App\Enums\DownloadSessionStatus;
use App\Models\DownloadSession;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for cleaning up extraction data.
 *
 * This job handles cleanup of expired download sessions,
 * temporary files, and old extraction data.
 */
class CleanupExtractionData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     *
     * @param  array  $options  Cleanup options
     */
    public function __construct(
        private array $options = []
    ) {
        // Set default options
        $this->options = array_merge([
            'cleanup_expired' => true,
            'cleanup_old_failed' => true,
            'cleanup_old_completed' => false,
            'expired_threshold_hours' => 24,
            'failed_threshold_days' => 7,
            'completed_threshold_days' => 30,
            'batch_size' => 100,
        ], $this->options);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting extraction data cleanup job', [
            'options' => $this->options,
        ]);

        $cleanupStats = [
            'expired_sessions' => 0,
            'old_failed_sessions' => 0,
            'old_completed_sessions' => 0,
            'total_cleaned' => 0,
        ];

        try {
            if ($this->options['cleanup_expired']) {
                $cleanupStats['expired_sessions'] = $this->cleanupExpiredSessions();
            }

            if ($this->options['cleanup_old_failed']) {
                $cleanupStats['old_failed_sessions'] = $this->cleanupOldFailedSessions();
            }

            if ($this->options['cleanup_old_completed']) {
                $cleanupStats['old_completed_sessions'] = $this->cleanupOldCompletedSessions();
            }

            $cleanupStats['total_cleaned'] = array_sum([
                $cleanupStats['expired_sessions'],
                $cleanupStats['old_failed_sessions'],
                $cleanupStats['old_completed_sessions'],
            ]);

            Log::info('Extraction data cleanup completed', $cleanupStats);

        } catch (\Throwable $exception) {
            Log::error('Extraction data cleanup failed', [
                'exception' => $exception->getMessage(),
                'stats' => $cleanupStats,
            ]);

            throw $exception;
        }
    }

    /**
     * Clean up expired download sessions.
     */
    private function cleanupExpiredSessions(): int
    {
        $threshold = Carbon::now()->subHours($this->options['expired_threshold_hours']);
        $cleaned = 0;

        Log::info('Cleaning up expired sessions', [
            'threshold' => $threshold->toISOString(),
        ]);

        do {
            $sessions = DownloadSession::where(function ($query) use ($threshold) {
                $query->where('expires_at', '<', Carbon::now())
                    ->orWhere(function ($subQuery) use ($threshold) {
                        $subQuery
                            ->where('updated_at', '<', $threshold)
                            ->whereNull('expires_at');
                    });
            })
                ->limit($this->options['batch_size'])
                ->get();

            foreach ($sessions as $session) {
                try {
                    // Update status to expired instead of deleting
                    $session->update([
                        'status' => DownloadSessionStatus::FAILED,
                        'download_url' => null, // Clear the download URL
                        'error_message' => 'Session expired and cleaned up',
                    ]);

                    $cleaned++;

                    Log::debug('Session marked as expired', [
                        'session_id' => $session->id,
                        'original_status' => $session->getOriginal('status'),
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Failed to expire session', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } while ($sessions->count() === $this->options['batch_size']);

        return $cleaned;
    }

    /**
     * Clean up old failed sessions.
     */
    private function cleanupOldFailedSessions(): int
    {
        $threshold = Carbon::now()->subDays($this->options['failed_threshold_days']);
        $cleaned = 0;

        Log::info('Cleaning up old failed sessions', [
            'threshold' => $threshold->toISOString(),
        ]);

        do {
            $sessions = DownloadSession::where('status', DownloadSessionStatus::FAILED)
                ->where('updated_at', '<', $threshold)
                ->limit($this->options['batch_size'])
                ->get();

            foreach ($sessions as $session) {
                try {
                    // For failed sessions, we can safely delete them after the threshold
                    $session->delete();
                    $cleaned++;

                    Log::debug('Failed session deleted', [
                        'session_id' => $session->id,
                        'failed_at' => $session->updated_at->toISOString(),
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Failed to delete failed session', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } while ($sessions->count() === $this->options['batch_size']);

        return $cleaned;
    }

    /**
     * Clean up old completed sessions.
     */
    private function cleanupOldCompletedSessions(): int
    {
        $threshold = Carbon::now()->subDays($this->options['completed_threshold_days']);
        $cleaned = 0;

        Log::info('Cleaning up old completed sessions', [
            'threshold' => $threshold->toISOString(),
        ]);

        do {
            $sessions = DownloadSession::where('status', DownloadSessionStatus::COMPLETED)
                ->where('updated_at', '<', $threshold)
                ->limit($this->options['batch_size'])
                ->get();

            foreach ($sessions as $session) {
                try {
                    // For very old completed sessions, clear sensitive data but keep record
                    $session->update([
                        'download_url' => null,
                        'thumbnail_url' => null,
                        'error_message' => 'Data cleaned up due to age',
                    ]);

                    $cleaned++;

                    Log::debug('Completed session data cleared', [
                        'session_id' => $session->id,
                        'completed_at' => $session->updated_at->toISOString(),
                    ]);

                } catch (\Exception $e) {
                    Log::warning('Failed to clean completed session data', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

        } while ($sessions->count() === $this->options['batch_size']);

        return $cleaned;
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Cleanup job failed permanently', [
            'exception' => $exception->getMessage(),
            'options' => $this->options,
        ]);
    }
}
