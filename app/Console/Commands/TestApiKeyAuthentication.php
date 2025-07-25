<?php

namespace App\Console\Commands;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\ApiKeyCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Console command to test API key authentication system.
 */
class TestApiKeyAuthentication extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'api-auth:test {--create-key} {--test-endpoints} {--test-cache} {--cleanup}';

    /**
     * The console command description.
     */
    protected $description = 'Test API key authentication system';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing API Key Authentication System');
        $this->newLine();

        if ($this->option('create-key')) {
            $this->testCreateApiKey();
        }

        if ($this->option('test-endpoints')) {
            $this->testEndpoints();
        }

        if ($this->option('test-cache')) {
            $this->testCaching();
        }

        if ($this->option('cleanup')) {
            $this->cleanup();
        }

        if (! $this->hasOptions()) {
            $this->runAllTests();
        }

        return self::SUCCESS;
    }

    /**
     * Run all tests.
     */
    private function runAllTests(): void
    {
        $this->testCreateApiKey();
        $this->testEndpoints();
        $this->testCaching();
        $this->cleanup();
    }

    /**
     * Test creating API keys.
     */
    private function testCreateApiKey(): void
    {
        $this->info('🔑 Testing API Key Creation...');

        try {
            $apiKey = $this->createTestApiKey();

            $this->line('✅ API Key created successfully:');
            $this->line("   ID: {$apiKey->id}");
            $this->line("   Name: {$apiKey->name}");
            $this->line("   Tier: {$apiKey->tier}");
            $this->line("   Status: {$apiKey->status->value}");
            $this->line("   Daily Limit: {$apiKey->daily_limit}");
            $this->line("   Monthly Limit: {$apiKey->monthly_limit}");

        } catch (\Exception $e) {
            $this->error("❌ Failed to create API key: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Test API endpoints.
     */
    private function testEndpoints(): void
    {
        $this->info('🌐 Testing API Endpoints...');

        $apiKey = $this->getOrCreateTestApiKey();
        $testKey = 'test_key_'.$apiKey->id;

        // Update the API key hash to match our test key
        $apiKey->update(['key_hash' => hash('sha256', $testKey)]);

        $baseUrl = config('app.url');

        // Test public endpoint (should work without API key)
        $this->testPublicEndpoint($baseUrl);

        // Test protected endpoints
        $this->testProtectedEndpoints($baseUrl, $testKey);

        $this->newLine();
    }

    /**
     * Test caching functionality.
     */
    private function testCaching(): void
    {
        $this->info('💾 Testing Cache Functionality...');

        $cacheService = app(ApiKeyCacheService::class);
        $apiKey = $this->getOrCreateTestApiKey();
        $keyHash = hash('sha256', 'test_cache_key');

        try {
            // Test caching
            $this->line('Testing cache operations...');

            $cacheService->cacheApiKey($keyHash, $apiKey);
            $this->line('✅ API key cached successfully');

            // Test retrieval
            $cachedKey = $cacheService->getCachedApiKey($keyHash);
            if ($cachedKey && $cachedKey->id === $apiKey->id) {
                $this->line('✅ API key retrieved from cache successfully');
            } else {
                $this->error('❌ Failed to retrieve API key from cache');
            }

            // Test cache statistics
            $stats = $cacheService->getCacheStatistics();
            $this->line('✅ Cache statistics retrieved:');
            $this->line("   Cache Driver: {$stats['cache_driver']}");
            $this->line("   Cache TTL: {$stats['cache_ttl']} seconds");

            // Test invalidation
            $cacheService->invalidateApiKey($keyHash);
            if (! $cacheService->isCached($keyHash)) {
                $this->line('✅ Cache invalidation successful');
            } else {
                $this->error('❌ Cache invalidation failed');
            }

        } catch (\Exception $e) {
            $this->error("❌ Cache test failed: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Test public endpoint.
     */
    private function testPublicEndpoint(string $baseUrl): void
    {
        try {
            $response = Http::get("{$baseUrl}/api/v1/extract/platforms");

            if ($response->successful()) {
                $this->line('✅ Public endpoint accessible without API key');
            } else {
                $this->error("❌ Public endpoint failed: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Public endpoint test failed: {$e->getMessage()}");
        }
    }

    /**
     * Test protected endpoints.
     */
    private function testProtectedEndpoints(string $baseUrl, string $testKey): void
    {
        // Test without API key
        try {
            $response = Http::post("{$baseUrl}/api/v1/extract", [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

            if ($response->status() === 401) {
                $this->line('✅ Protected endpoint correctly rejects requests without API key');
            } else {
                $this->error("❌ Protected endpoint should return 401, got: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Protected endpoint test failed: {$e->getMessage()}");
        }

        // Test with valid API key
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$testKey}",
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/api/v1/extract", [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'quality' => '720p',
                'format' => 'mp4',
            ]);

            if ($response->successful()) {
                $this->line('✅ Protected endpoint accepts valid API key');
                $data = $response->json();
                if (isset($data['data']['session_id'])) {
                    $this->line("   Session ID: {$data['data']['session_id']}");
                }
            } else {
                $this->error("❌ Protected endpoint failed with valid API key: {$response->status()}");
                $this->error('   Response: '.$response->body());
            }
        } catch (\Exception $e) {
            $this->error("❌ Protected endpoint test with API key failed: {$e->getMessage()}");
        }

        // Test with invalid API key
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer invalid_key_12345',
            ])->post("{$baseUrl}/api/v1/extract", [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ]);

            if ($response->status() === 401) {
                $this->line('✅ Protected endpoint correctly rejects invalid API key');
            } else {
                $this->error("❌ Protected endpoint should return 401 for invalid key, got: {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->error("❌ Invalid API key test failed: {$e->getMessage()}");
        }
    }

    /**
     * Create a test API key.
     */
    private function createTestApiKey(): ApiKey
    {
        return ApiKey::create([
            'name' => 'Test API Key - '.now()->format('Y-m-d H:i:s'),
            'key_hash' => hash('sha256', 'test_key_'.Str::random(16)),
            'tier' => 'basic',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 100,
            'monthly_limit' => 1000,
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
            'allowed_qualities' => ['144p', '360p', '720p', '1080p'],
            'allowed_formats' => ['mp4', 'webm', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);
    }

    /**
     * Get or create a test API key.
     */
    private function getOrCreateTestApiKey(): ApiKey
    {
        $testApiKey = ApiKey::where('name', 'LIKE', 'Test API Key%')->first();

        if (! $testApiKey) {
            $testApiKey = $this->createTestApiKey();
        }

        return $testApiKey;
    }

    /**
     * Cleanup test data.
     */
    private function cleanup(): void
    {
        $this->info('🧹 Cleaning up test data...');

        try {
            // Clear cache
            Cache::flush();
            $this->line('✅ Cache cleared');

            // Remove test API keys
            $deleted = ApiKey::where('name', 'LIKE', 'Test API Key%')->delete();
            $this->line("✅ Deleted {$deleted} test API keys");

        } catch (\Exception $e) {
            $this->error("❌ Cleanup failed: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Check if any options are provided.
     */
    private function hasOptions(): bool
    {
        return $this->option('create-key') ||
               $this->option('test-endpoints') ||
               $this->option('test-cache') ||
               $this->option('cleanup');
    }
}
