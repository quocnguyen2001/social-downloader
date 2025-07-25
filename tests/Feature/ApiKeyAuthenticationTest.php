<?php

namespace Tests\Feature;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\ApiKeyCacheService;
use App\Services\AuthenticatedApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Test suite for API key authentication middleware.
 */
class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $testApiKey;

    private string $testApiKeyHash;

    private ApiKey $apiKeyModel;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate test API key
        $this->testApiKey = 'test_api_key_'.uniqid();
        $this->testApiKeyHash = hash('sha256', $this->testApiKey);

        // Create test API key model
        $this->apiKeyModel = ApiKey::create([
            'name' => 'Test API Key',
            'key_hash' => $this->testApiKeyHash,
            'tier' => 'basic',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 100,
            'monthly_limit' => 1000,
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'allowed_platforms' => ['youtube', 'tiktok'],
            'allowed_qualities' => ['720p', '1080p'],
            'allowed_formats' => ['mp4', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        // Clear cache, rate limiter, and singleton before each test
        Cache::flush();
        RateLimiter::clear('api_rate_limit:'.$this->apiKeyModel->id);
        AuthenticatedApiKey::clear();
    }

    /** @test */
    public function it_allows_access_with_valid_api_key_in_bearer_token()
    {
        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'quality' => '720p',
            'format' => 'mp4',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_allows_access_with_valid_api_key_in_header()
    {
        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'quality' => '720p',
            'format' => 'mp4',
        ], [
            'X-API-Key' => $this->testApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_denies_access_without_api_key()
    {
        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'API key is required',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function it_denies_access_with_invalid_api_key()
    {
        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer invalid_api_key',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid API key',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function it_denies_access_with_inactive_api_key()
    {
        $this->apiKeyModel->update(['status' => ApiKeyStatus::INACTIVE]);

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'API key is not active',
                'error_code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function it_enforces_daily_usage_limits()
    {
        $this->apiKeyModel->update([
            'daily_usage' => 100,
            'daily_limit' => 100,
        ]);

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Daily usage limit exceeded',
            ]);
    }

    /** @test */
    public function it_enforces_monthly_usage_limits()
    {
        $this->apiKeyModel->update([
            'monthly_usage' => 1000,
            'monthly_limit' => 1000,
        ]);

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Monthly usage limit exceeded',
            ]);
    }

    /** @test */
    public function it_enforces_rate_limits()
    {
        // Make requests up to the rate limit
        for ($i = 0; $i < 60; $i++) { // Basic tier limit is 60 per minute
            RateLimiter::hit('api_rate_limit:'.$this->apiKeyModel->id, 60);
        }

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Rate limit exceeded',
            ]);
    }

    /** @test */
    public function it_caches_api_key_data()
    {
        $cacheService = app(ApiKeyCacheService::class);

        // First request should query database and cache
        $this->assertFalse($cacheService->isCached($this->testApiKeyHash));

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200);

        // API key should now be cached
        $this->assertTrue($cacheService->isCached($this->testApiKeyHash));

        // Second request should use cache
        $cachedApiKey = $cacheService->getCachedApiKey($this->testApiKeyHash);
        $this->assertNotNull($cachedApiKey);
        $this->assertEquals($this->apiKeyModel->id, $cachedApiKey->id);
    }

    /** @test */
    public function it_caches_invalid_api_keys()
    {
        $invalidKey = 'invalid_key_'.uniqid();
        $invalidKeyHash = hash('sha256', $invalidKey);
        $cacheService = app(ApiKeyCacheService::class);

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$invalidKey,
        ]);

        $response->assertStatus(401);

        // Invalid key should be cached as false
        $cachedResult = $cacheService->getCachedApiKey($invalidKeyHash);
        $this->assertFalse($cachedResult);
    }

    /** @test */
    public function it_allows_access_to_public_endpoints_without_api_key()
    {
        $response = $this->getJson('/api/v1/extract/platforms');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_restricts_session_access_to_owner()
    {
        // Create a session with our API key
        $session = \App\Models\DownloadSession::create([
            'api_key_id' => $this->apiKeyModel->id,
            'original_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'platform' => \App\Enums\Platform::YOUTUBE,
            'quality' => \App\Enums\VideoQuality::Q720P,
            'format' => \App\Enums\VideoFormat::MP4,
            'status' => \App\Enums\DownloadSessionStatus::PENDING,
        ]);

        // Create another API key
        $otherApiKey = ApiKey::create([
            'name' => 'Other API Key',
            'key_hash' => hash('sha256', 'other_key'),
            'tier' => 'basic',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 100,
            'monthly_limit' => 1000,
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'allowed_platforms' => ['youtube'],
            'allowed_qualities' => ['720p'],
            'allowed_formats' => ['mp4'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        // Try to access session with different API key
        $response = $this->getJson("/api/v1/extract/status/{$session->id}", [
            'Authorization' => 'Bearer other_key',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied to this session',
            ]);

        // Access with correct API key should work
        $response = $this->getJson("/api/v1/extract/status/{$session->id}", [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function it_handles_different_tier_rate_limits()
    {
        // Test premium tier
        $premiumApiKey = ApiKey::create([
            'name' => 'Premium API Key',
            'key_hash' => hash('sha256', 'premium_key'),
            'tier' => 'premium',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 10000,
            'monthly_limit' => 100000,
            'daily_usage' => 0,
            'monthly_usage' => 0,
            'total_usage' => 0,
            'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
            'allowed_qualities' => ['144p', '360p', '720p', '1080p'],
            'allowed_formats' => ['mp4', 'webm', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        // Premium should have higher rate limit (1000 vs 60 for basic)
        for ($i = 0; $i < 100; $i++) {
            $response = $this->postJson('/api/v1/extract', [
                'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            ], [
                'Authorization' => 'Bearer premium_key',
            ]);

            // Should not hit rate limit yet
            $this->assertNotEquals(429, $response->getStatusCode());
        }
    }

    /** @test */
    public function singleton_is_cleared_after_each_request()
    {
        // Ensure singleton is empty before request
        $this->assertFalse(AuthenticatedApiKey::has());

        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'quality' => '720p',
            'format' => 'mp4',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200);

        // After request completion, singleton should be cleared
        $this->assertFalse(AuthenticatedApiKey::has());
        $this->assertNull(AuthenticatedApiKey::get());
    }
}
