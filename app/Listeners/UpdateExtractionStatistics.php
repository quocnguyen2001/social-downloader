<?php

namespace App\Listeners;

use App\Events\ExtractionCompleted;
use App\Events\ExtractionFailed;
use App\Models\ApiKey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Listener for updating extraction statistics.
 *
 * This listener responds to extraction completion and failure events
 * to update usage statistics and metrics.
 */
class UpdateExtractionStatistics implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the listener may be attempted.
     */
    public int $tries = 2;

    /**
     * Handle extraction completed event.
     */
    public function handleCompleted(ExtractionCompleted $event): void
    {
        Log::info('Updating statistics for completed extraction', [
            'download_session_id' => $event->getDownloadSessionId(),
            'platform' => $event->getPlatform()->value,
            'processing_time' => $event->getProcessingTime(),
        ]);

        try {
            $this->updateApiKeyUsage($event->getDownloadSession()->apiKey);
            $this->updatePlatformStatistics($event->getPlatform()->value, 'completed');
            $this->updatePerformanceMetrics($event);
            $this->updateDailyStatistics('completed');

        } catch (\Throwable $exception) {
            Log::error('Failed to update statistics for completed extraction', [
                'download_session_id' => $event->getDownloadSessionId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Handle extraction failed event.
     */
    public function handleFailed(ExtractionFailed $event): void
    {
        Log::info('Updating statistics for failed extraction', [
            'download_session_id' => $event->getDownloadSessionId(),
            'platform' => $event->getPlatform()->value,
            'failure_category' => $event->getFailureCategory(),
        ]);

        try {
            $this->updateApiKeyUsage($event->getDownloadSession()->apiKey, false);
            $this->updatePlatformStatistics($event->getPlatform()->value, 'failed');
            $this->updateFailureStatistics($event);
            $this->updateDailyStatistics('failed');

        } catch (\Throwable $exception) {
            Log::error('Failed to update statistics for failed extraction', [
                'download_session_id' => $event->getDownloadSessionId(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Update API key usage statistics.
     */
    private function updateApiKeyUsage(?ApiKey $apiKey, bool $successful = true): void
    {
        if (! $apiKey) {
            return; // No API key to update
        }

        try {
            // Update daily usage
            $apiKey->increment('daily_usage');

            // Update monthly usage
            $apiKey->increment('monthly_usage');

            // Update total usage
            $apiKey->increment('total_usage');

            // Reset counters if needed
            $this->resetUsageCountersIfNeeded($apiKey);

            Log::debug('API key usage updated', [
                'api_key_id' => $apiKey->id,
                'daily_usage' => $apiKey->daily_usage,
                'monthly_usage' => $apiKey->monthly_usage,
                'successful' => $successful,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to update API key usage', [
                'api_key_id' => $apiKey->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update platform-specific statistics.
     */
    private function updatePlatformStatistics(string $platform, string $status): void
    {
        $cacheKey = "platform_stats:{$platform}:".now()->format('Y-m-d');

        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
        ]);

        $stats['total']++;
        $stats[$status]++;

        Cache::put($cacheKey, $stats, now()->addDays(7));

        Log::debug('Platform statistics updated', [
            'platform' => $platform,
            'status' => $status,
            'stats' => $stats,
        ]);
    }

    /**
     * Update performance metrics.
     */
    private function updatePerformanceMetrics(ExtractionCompleted $event): void
    {
        $platform = $event->getPlatform()->value;
        $processingTime = $event->getProcessingTime();

        $cacheKey = "performance_metrics:{$platform}:".now()->format('Y-m-d');

        $metrics = Cache::get($cacheKey, [
            'count' => 0,
            'total_time' => 0,
            'min_time' => null,
            'max_time' => null,
            'avg_time' => 0,
        ]);

        $metrics['count']++;
        $metrics['total_time'] += $processingTime;
        $metrics['min_time'] = $metrics['min_time'] === null ? $processingTime : min($metrics['min_time'], $processingTime);
        $metrics['max_time'] = $metrics['max_time'] === null ? $processingTime : max($metrics['max_time'], $processingTime);
        $metrics['avg_time'] = $metrics['total_time'] / $metrics['count'];

        Cache::put($cacheKey, $metrics, now()->addDays(7));

        Log::debug('Performance metrics updated', [
            'platform' => $platform,
            'processing_time' => $processingTime,
            'avg_time' => $metrics['avg_time'],
        ]);
    }

    /**
     * Update failure statistics.
     */
    private function updateFailureStatistics(ExtractionFailed $event): void
    {
        $platform = $event->getPlatform()->value;
        $failureCategory = $event->getFailureCategory();

        $cacheKey = "failure_stats:{$platform}:".now()->format('Y-m-d');

        $stats = Cache::get($cacheKey, []);

        if (! isset($stats[$failureCategory])) {
            $stats[$failureCategory] = 0;
        }

        $stats[$failureCategory]++;

        Cache::put($cacheKey, $stats, now()->addDays(7));

        Log::debug('Failure statistics updated', [
            'platform' => $platform,
            'failure_category' => $failureCategory,
            'count' => $stats[$failureCategory],
        ]);
    }

    /**
     * Update daily statistics.
     */
    private function updateDailyStatistics(string $status): void
    {
        $cacheKey = 'daily_stats:'.now()->format('Y-m-d');

        $stats = Cache::get($cacheKey, [
            'total' => 0,
            'completed' => 0,
            'failed' => 0,
        ]);

        $stats['total']++;
        $stats[$status]++;

        Cache::put($cacheKey, $stats, now()->addDays(7));

        Log::debug('Daily statistics updated', [
            'status' => $status,
            'stats' => $stats,
        ]);
    }

    /**
     * Reset usage counters if needed.
     */
    private function resetUsageCountersIfNeeded(ApiKey $apiKey): void
    {
        $now = now();

        // Reset daily usage if it's a new day
        if ($apiKey->last_reset_daily->format('Y-m-d') !== $now->format('Y-m-d')) {
            $apiKey->update([
                'daily_usage' => 1, // Set to 1 since we just incremented
                'last_reset_daily' => $now->toDateString(),
            ]);
        }

        // Reset monthly usage if it's a new month
        if ($apiKey->last_reset_monthly->format('Y-m') !== $now->format('Y-m')) {
            $apiKey->update([
                'monthly_usage' => 1, // Set to 1 since we just incremented
                'last_reset_monthly' => $now->toDateString(),
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed($event, \Throwable $exception): void
    {
        Log::error('Statistics update listener failed permanently', [
            'event_class' => get_class($event),
            'exception' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the listener.
     */
    public function tags(): array
    {
        return ['statistics', 'listener', 'metrics'];
    }
}
