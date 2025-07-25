<?php

namespace Tests\Feature;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\AuthenticatedApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integration test suite for AuthenticatedApiKey singleton with middleware.
 */
class AuthenticatedApiKeySingletonIntegrationTest extends TestCase
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
            'tier' => 'pro',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 500,
            'monthly_limit' => 10000,
            'daily_usage' => 10,
            'monthly_usage' => 100,
            'total_usage' => 1000,
            'allowed_platforms' => ['youtube', 'tiktok'],
            'allowed_qualities' => ['720p', '1080p'],
            'allowed_formats' => ['mp4', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        // Clear cache and singleton
        Cache::flush();
        AuthenticatedApiKey::clear();
    }

    protected function tearDown(): void
    {
        AuthenticatedApiKey::clear();
        parent::tearDown();
    }

    /** @test */
    public function middleware_sets_api_key_in_singleton()
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

        // During the request, the singleton should have been set
        // Note: After the request completes, the cleanup middleware clears it
        // So we need to test this differently by creating a custom test route
    }

    /** @test */
    public function singleton_is_accessible_in_controller()
    {
        // Create a test route that checks the singleton
        $this->app['router']->post('/test/singleton-check', function () {
            $apiKey = AuthenticatedApiKey::get();

            return response()->json([
                'has_api_key' => AuthenticatedApiKey::has(),
                'api_key_id' => AuthenticatedApiKey::getId(),
                'tier' => AuthenticatedApiKey::getTier(),
                'name' => AuthenticatedApiKey::getName(),
                'authentication_method' => AuthenticatedApiKey::getAuthenticationMethod(),
                'request_id' => AuthenticatedApiKey::getRequestId(),
                'usage_stats' => AuthenticatedApiKey::getUsageStats(),
                'debug_info' => AuthenticatedApiKey::getDebugInfo(),
            ]);
        })->middleware('api.auth');

        $response = $this->postJson('/test/singleton-check', [], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['has_api_key']);
        $this->assertEquals($this->apiKeyModel->id, $data['api_key_id']);
        $this->assertEquals('pro', $data['tier']);
        $this->assertEquals('Test API Key', $data['name']);
        $this->assertEquals('bearer_token', $data['authentication_method']);
        $this->assertNotNull($data['request_id']);
        $this->assertIsArray($data['usage_stats']);
        $this->assertEquals(10, $data['usage_stats']['daily_usage']);
        $this->assertEquals(500, $data['usage_stats']['daily_limit']);
        $this->assertIsArray($data['debug_info']);
    }

    /** @test */
    public function singleton_includes_correct_metadata()
    {
        $this->app['router']->post('/test/metadata-check', function () {
            return response()->json([
                'metadata' => AuthenticatedApiKey::getMetadata(),
                'ip_address' => AuthenticatedApiKey::getIpAddress(),
                'user_agent' => AuthenticatedApiKey::getUserAgent(),
                'authenticated_at' => AuthenticatedApiKey::getAuthenticatedAt(),
            ]);
        })->middleware('api.auth');

        $response = $this->postJson('/test/metadata-check', [], [
            'Authorization' => 'Bearer '.$this->testApiKey,
            'User-Agent' => 'Test User Agent',
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertIsArray($data['metadata']);
        $this->assertEquals('bearer_token', $data['metadata']['authentication_method']);
        $this->assertEquals('/test/metadata-check', $data['metadata']['endpoint']);
        $this->assertNotNull($data['authenticated_at']);
        $this->assertEquals('Test User Agent', $data['user_agent']);
    }

    /** @test */
    public function singleton_works_with_x_api_key_header()
    {
        $this->app['router']->post('/test/header-auth', function () {
            return response()->json([
                'authentication_method' => AuthenticatedApiKey::getAuthenticationMethod(),
                'api_key_id' => AuthenticatedApiKey::getId(),
            ]);
        })->middleware('api.auth');

        $response = $this->postJson('/test/header-auth', [], [
            'X-API-Key' => $this->testApiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals('api_key_header', $data['authentication_method']);
        $this->assertEquals($this->apiKeyModel->id, $data['api_key_id']);
    }

    /** @test */
    public function singleton_is_cleared_after_request()
    {
        // Make a request that sets the singleton
        $response = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200);

        // After the request, the singleton should be cleared
        $this->assertFalse(AuthenticatedApiKey::has());
        $this->assertNull(AuthenticatedApiKey::get());
    }

    /** @test */
    public function singleton_handles_multiple_requests_independently()
    {
        // Create another API key
        $anotherApiKey = 'another_key_'.uniqid();
        $anotherApiKeyModel = ApiKey::create([
            'name' => 'Another API Key',
            'key_hash' => hash('sha256', $anotherApiKey),
            'tier' => 'basic',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 100,
            'monthly_limit' => 1000,
            'daily_usage' => 5,
            'monthly_usage' => 50,
            'total_usage' => 500,
            'allowed_platforms' => ['youtube'],
            'allowed_qualities' => ['720p'],
            'allowed_formats' => ['mp4'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        $this->app['router']->post('/test/multi-request', function () {
            return response()->json([
                'api_key_id' => AuthenticatedApiKey::getId(),
                'tier' => AuthenticatedApiKey::getTier(),
                'name' => AuthenticatedApiKey::getName(),
            ]);
        })->middleware('api.auth');

        // First request with first API key
        $response1 = $this->postJson('/test/multi-request', [], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response1->assertStatus(200);
        $data1 = $response1->json();
        $this->assertEquals($this->apiKeyModel->id, $data1['api_key_id']);
        $this->assertEquals('pro', $data1['tier']);
        $this->assertEquals('Test API Key', $data1['name']);

        // Second request with different API key
        $response2 = $this->postJson('/test/multi-request', [], [
            'Authorization' => 'Bearer '.$anotherApiKey,
        ]);

        $response2->assertStatus(200);
        $data2 = $response2->json();
        $this->assertEquals($anotherApiKeyModel->id, $data2['api_key_id']);
        $this->assertEquals('basic', $data2['tier']);
        $this->assertEquals('Another API Key', $data2['name']);

        // Verify they're different
        $this->assertNotEquals($data1['api_key_id'], $data2['api_key_id']);
        $this->assertNotEquals($data1['tier'], $data2['tier']);
    }

    /** @test */
    public function singleton_provides_consistent_data_throughout_request()
    {
        $this->app['router']->post('/test/consistency', function () {
            // Call multiple times during the same request
            $calls = [];
            for ($i = 0; $i < 5; $i++) {
                $calls[] = [
                    'api_key_id' => AuthenticatedApiKey::getId(),
                    'tier' => AuthenticatedApiKey::getTier(),
                    'request_id' => AuthenticatedApiKey::getRequestId(),
                    'has_api_key' => AuthenticatedApiKey::has(),
                ];

                // Small delay to ensure time passes
                usleep(100);
            }

            return response()->json(['calls' => $calls]);
        })->middleware('api.auth');

        $response = $this->postJson('/test/consistency', [], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $response->assertStatus(200);
        $data = $response->json();

        // All calls should return the same data
        $firstCall = $data['calls'][0];
        foreach ($data['calls'] as $call) {
            $this->assertEquals($firstCall['api_key_id'], $call['api_key_id']);
            $this->assertEquals($firstCall['tier'], $call['tier']);
            $this->assertEquals($firstCall['request_id'], $call['request_id']);
            $this->assertTrue($call['has_api_key']);
        }
    }

    /** @test */
    public function singleton_works_with_video_extraction_controller()
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

        $data = $response->json();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('session_id', $data['data']);

        // Test status endpoint with the same API key
        $sessionId = $data['data']['session_id'];
        $statusResponse = $this->getJson("/api/v1/extract/status/{$sessionId}", [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $statusResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test */
    public function singleton_prevents_cross_session_access()
    {
        // Create session with first API key
        $response1 = $this->postJson('/api/v1/extract', [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ], [
            'Authorization' => 'Bearer '.$this->testApiKey,
        ]);

        $sessionId = $response1->json()['data']['session_id'];

        // Create another API key
        $anotherApiKey = 'another_key_'.uniqid();
        ApiKey::create([
            'name' => 'Another API Key',
            'key_hash' => hash('sha256', $anotherApiKey),
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
        $response2 = $this->getJson("/api/v1/extract/status/{$sessionId}", [
            'Authorization' => 'Bearer '.$anotherApiKey,
        ]);

        $response2->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Access denied to this session',
            ]);
    }
}
