<?php

namespace App\Console\Commands;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\AuthenticatedApiKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Console command to test the AuthenticatedApiKey singleton functionality.
 */
class TestAuthenticatedApiKeySingleton extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'singleton:test {--create-key} {--test-methods} {--test-integration} {--cleanup}';

    /**
     * The console command description.
     */
    protected $description = 'Test AuthenticatedApiKey singleton functionality';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing AuthenticatedApiKey Singleton');
        $this->newLine();

        if ($this->option('create-key')) {
            $this->testCreateApiKey();
        }

        if ($this->option('test-methods')) {
            $this->testSingletonMethods();
        }

        if ($this->option('test-integration')) {
            $this->testIntegration();
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
        $this->testSingletonMethods();
        $this->testIntegration();
        $this->cleanup();
    }

    /**
     * Test creating API keys.
     */
    private function testCreateApiKey(): void
    {
        $this->info('🔑 Testing API Key Creation for Singleton Tests...');

        try {
            $apiKey = $this->createTestApiKey();

            $this->line('✅ API Key created successfully:');
            $this->line("   ID: {$apiKey->id}");
            $this->line("   Name: {$apiKey->name}");
            $this->line("   Tier: {$apiKey->tier}");

        } catch (\Exception $e) {
            $this->error("❌ Failed to create API key: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Test singleton methods.
     */
    private function testSingletonMethods(): void
    {
        $this->info('🔧 Testing Singleton Methods...');

        try {
            // Clear singleton first
            AuthenticatedApiKey::clear();

            // Test empty state
            $this->line('Testing empty state...');
            if (! AuthenticatedApiKey::has() && AuthenticatedApiKey::get() === null) {
                $this->line('✅ Empty state working correctly');
            } else {
                $this->error('❌ Empty state not working');
            }

            // Create and set API key
            $apiKey = $this->getOrCreateTestApiKey();
            $metadata = [
                'authentication_method' => 'test',
                'endpoint' => '/test/endpoint',
                'custom_field' => 'test_value',
            ];

            AuthenticatedApiKey::set($apiKey, $metadata);

            // Test basic methods
            $this->line('Testing basic methods...');
            if (AuthenticatedApiKey::has()) {
                $this->line('✅ has() method working');
            } else {
                $this->error('❌ has() method failed');
            }

            if (AuthenticatedApiKey::getId() === $apiKey->id) {
                $this->line('✅ getId() method working');
            } else {
                $this->error('❌ getId() method failed');
            }

            if (AuthenticatedApiKey::getTier() === $apiKey->tier) {
                $this->line('✅ getTier() method working');
            } else {
                $this->error('❌ getTier() method failed');
            }

            if (AuthenticatedApiKey::getName() === $apiKey->name) {
                $this->line('✅ getName() method working');
            } else {
                $this->error('❌ getName() method failed');
            }

            // Test metadata methods
            $this->line('Testing metadata methods...');
            if (AuthenticatedApiKey::getAuthenticationMethod() === 'test') {
                $this->line('✅ getAuthenticationMethod() working');
            } else {
                $this->error('❌ getAuthenticationMethod() failed');
            }

            if (AuthenticatedApiKey::getMetadata('custom_field') === 'test_value') {
                $this->line('✅ getMetadata() working');
            } else {
                $this->error('❌ getMetadata() failed');
            }

            // Test tier checking methods
            $this->line('Testing tier checking methods...');
            if (AuthenticatedApiKey::hasTier($apiKey->tier)) {
                $this->line('✅ hasTier() working');
            } else {
                $this->error('❌ hasTier() failed');
            }

            // Test usage stats
            $this->line('Testing usage statistics...');
            $usageStats = AuthenticatedApiKey::getUsageStats();
            if (is_array($usageStats) && isset($usageStats['daily_usage'])) {
                $this->line('✅ getUsageStats() working');
                $this->line("   Daily Usage: {$usageStats['daily_usage']}/{$usageStats['daily_limit']}");
                $this->line("   Monthly Usage: {$usageStats['monthly_usage']}/{$usageStats['monthly_limit']}");
            } else {
                $this->error('❌ getUsageStats() failed');
            }

            // Test debug info
            $this->line('Testing debug information...');
            $debugInfo = AuthenticatedApiKey::getDebugInfo();
            if (is_array($debugInfo) && $debugInfo['has_api_key']) {
                $this->line('✅ getDebugInfo() working');
                $this->line("   Request ID: {$debugInfo['request_id']}");
                $this->line("   Duration: {$debugInfo['set_duration_ms']}ms");
            } else {
                $this->error('❌ getDebugInfo() failed');
            }

            // Test toArray
            $this->line('Testing array conversion...');
            $array = AuthenticatedApiKey::toArray();
            if (is_array($array) && $array['id'] === $apiKey->id) {
                $this->line('✅ toArray() working');
            } else {
                $this->error('❌ toArray() failed');
            }

            // Test clear
            $this->line('Testing clear method...');
            AuthenticatedApiKey::clear();
            if (! AuthenticatedApiKey::has()) {
                $this->line('✅ clear() method working');
            } else {
                $this->error('❌ clear() method failed');
            }

        } catch (\Exception $e) {
            $this->error("❌ Singleton methods test failed: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Test integration with HTTP requests.
     */
    private function testIntegration(): void
    {
        $this->info('🌐 Testing Integration with HTTP Requests...');

        $apiKey = $this->getOrCreateTestApiKey();
        $testKey = 'test_singleton_key_'.$apiKey->id;

        // Update the API key hash to match our test key
        $apiKey->update(['key_hash' => hash('sha256', $testKey)]);

        $baseUrl = config('app.url');

        try {
            // Test that singleton is cleared before request
            AuthenticatedApiKey::clear();
            $this->line('✅ Singleton cleared before test');

            // Make authenticated request
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$testKey}",
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/api/v1/extract", [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'quality' => '720p',
                'format' => 'mp4',
            ]);

            if ($response->successful()) {
                $this->line('✅ Authenticated request successful');
                $data = $response->json();
                if (isset($data['data']['session_id'])) {
                    $this->line("   Session ID: {$data['data']['session_id']}");
                }
            } else {
                $this->error("❌ Authenticated request failed: {$response->status()}");
                $this->error('   Response: '.$response->body());
            }

            // Verify singleton is cleared after request
            if (! AuthenticatedApiKey::has()) {
                $this->line('✅ Singleton properly cleared after request');
            } else {
                $this->error('❌ Singleton not cleared after request');
            }

        } catch (\Exception $e) {
            $this->error("❌ Integration test failed: {$e->getMessage()}");
        }

        $this->newLine();
    }

    /**
     * Create a test API key.
     */
    private function createTestApiKey(): ApiKey
    {
        return ApiKey::create([
            'name' => 'Singleton Test API Key - '.now()->format('Y-m-d H:i:s'),
            'key_hash' => hash('sha256', 'singleton_test_key_'.Str::random(16)),
            'tier' => 'pro',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 500,
            'monthly_limit' => 10000,
            'daily_usage' => 25,
            'monthly_usage' => 250,
            'total_usage' => 2500,
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
        $testApiKey = ApiKey::where('name', 'LIKE', 'Singleton Test API Key%')->first();

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
        $this->info('🧹 Cleaning up singleton test data...');

        try {
            // Clear singleton
            AuthenticatedApiKey::clear();
            $this->line('✅ Singleton cleared');

            // Remove test API keys
            $deleted = ApiKey::where('name', 'LIKE', 'Singleton Test API Key%')->delete();
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
               $this->option('test-methods') ||
               $this->option('test-integration') ||
               $this->option('cleanup');
    }
}
