<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Services\ApiKeyCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job that recalculates API key usage counters from ApiRequest records.
 */
class UpdateApiKeyUsageFromRequests implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BILLABLE_ENDPOINTS = [
        '/api/v1/download/trigger',
    ];

    /**
     * The number of seconds before the job should timeout.
     */
    public int $timeout = 60;

    /**
     * Number of tries before failing the job.
     */
    public int $tries = 3;

    /**
     * @param  string  $apiKeyId  The API key that needs its usage aggregated
     */
    public function __construct(private readonly string $apiKeyId) {}

    public function handle(ApiKeyCacheService $cacheService): void
    {
        $apiKey = ApiKey::find($this->apiKeyId);

        if (! $apiKey) {
            Log::warning('Failed to aggregate API key usage – API key missing', [
                'api_key_id' => $this->apiKeyId,
            ]);

            return;
        }

        $now = now();
        $baseQuery = ApiRequest::forApiKey($apiKey->id)
            ->whereIn('endpoint', self::BILLABLE_ENDPOINTS);

        $dailyUsage = (clone $baseQuery)
            ->whereDate('created_at', $now->toDateString())
            ->count();

        $monthlyUsage = (clone $baseQuery)
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $totalUsage = (clone $baseQuery)->count();

        $apiKey->forceFill([
            'daily_usage' => $dailyUsage,
            'monthly_usage' => $monthlyUsage,
            'total_usage' => $totalUsage,
            'last_reset_daily' => $now->copy()->startOfDay(),
            'last_reset_monthly' => $now->copy()->startOfMonth(),
        ])->save();

        // Keep cached representation in sync so middleware sees fresh usage numbers.
        if ($apiKey->key_hash) {
            $cacheService->cacheApiKey($apiKey->key_hash, $apiKey);
        }

        Log::info('Aggregated API key usage from ApiRequest records', [
            'api_key_id' => $apiKey->id,
            'daily_usage' => $dailyUsage,
            'monthly_usage' => $monthlyUsage,
            'total_usage' => $totalUsage,
        ]);
    }

    public function uniqueId(): string
    {
        return $this->apiKeyId;
    }
}
