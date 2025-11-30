<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\UpdateApiKeyUsageFromRequests;
use App\Models\ApiRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiKeyUsageAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_usage_aggregation_job_when_api_request_is_created(): void
    {
        Queue::fake();

        $apiKey = $this->createApiKey();

        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'created_at' => now(),
        ]);

        Queue::assertPushed(UpdateApiKeyUsageFromRequests::class, function ($job) use ($apiKey) {
            return $job->uniqueId() === $apiKey->id;
        });
    }

    public function test_job_recalculates_usage_counters_from_api_requests(): void
    {
        Carbon::setTestNow($now = Carbon::create(2025, 1, 15, 12));

        $apiKey = $this->createApiKey([
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'last_reset_daily' => $now->copy()->subDay()->toDateString(),
            'last_reset_monthly' => $now->copy()->subMonths(2)->startOfMonth()->toDateString(),
        ]);

        // Two requests today (should count towards daily + monthly)
        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/download/trigger',
            'created_at' => $now,
        ]);
        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/download/trigger',
            'created_at' => $now->copy()->addHour(),
        ]);

        // One request earlier in the same month
        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/download/trigger',
            'created_at' => $now->copy()->subDays(2),
        ]);

        // One request from a previous month (should only affect total)
        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/download/trigger',
            'created_at' => $now->copy()->subMonths(2),
        ]);

        // Non-billable endpoint should be ignored
        ApiRequest::factory()->create([
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/extract',
            'created_at' => $now,
        ]);

        UpdateApiKeyUsageFromRequests::dispatchSync($apiKey->id);

        $apiKey->refresh();

        $this->assertSame(2, $apiKey->daily_usage);
        $this->assertSame(3, $apiKey->monthly_usage);
        $this->assertSame(4, (int) $apiKey->total_usage);
        $this->assertEquals($now->copy()->startOfDay()->toDateString(), $apiKey->last_reset_daily->toDateString());
        $this->assertEquals($now->copy()->startOfMonth()->toDateString(), $apiKey->last_reset_monthly->toDateString());

        Carbon::setTestNow();
    }

    private function createApiKey(array $overrides = [])
    {
        return \App\Models\ApiKey::create(array_merge([
            'name' => 'Test API Key',
            'key_hash' => hash('sha256', 'test-key-'.Str::uuid()),
            'key_prefix' => 'vd_live_',
            'status' => 'active',
            'price_per_request' => 0.01,
            'daily_limit' => 1000,
            'monthly_limit' => 30000,
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->startOfMonth()->toDateString(),
        ], $overrides));
    }
}
