<?php

namespace Tests\Unit;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\AuthenticatedApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test suite for AuthenticatedApiKey singleton service.
 */
class AuthenticatedApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private ApiKey $testApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear singleton before each test
        AuthenticatedApiKey::clear();

        // Create test API key
        $this->testApiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key_hash' => 'test_hash_123',
            'tier' => 'pro',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 500,
            'monthly_limit' => 10000,
            'daily_usage' => 25,
            'monthly_usage' => 150,
            'total_usage' => 1500,
            'allowed_platforms' => ['youtube', 'tiktok'],
            'allowed_qualities' => ['720p', '1080p'],
            'allowed_formats' => ['mp4', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        // Clear singleton after each test
        AuthenticatedApiKey::clear();
        parent::tearDown();
    }

    /** @test */
    public function it_can_set_and_get_api_key()
    {
        $this->assertFalse(AuthenticatedApiKey::has());
        $this->assertNull(AuthenticatedApiKey::get());

        AuthenticatedApiKey::set($this->testApiKey);

        $this->assertTrue(AuthenticatedApiKey::has());
        $retrievedApiKey = AuthenticatedApiKey::get();
        $this->assertInstanceOf(ApiKey::class, $retrievedApiKey);
        $this->assertEquals($this->testApiKey->id, $retrievedApiKey->id);
    }

    /** @test */
    public function it_can_set_api_key_with_metadata()
    {
        $metadata = [
            'authentication_method' => 'bearer_token',
            'endpoint' => '/api/v1/extract',
            'custom_field' => 'custom_value',
        ];

        AuthenticatedApiKey::set($this->testApiKey, $metadata);

        $this->assertEquals('bearer_token', AuthenticatedApiKey::getAuthenticationMethod());
        $this->assertEquals('/api/v1/extract', AuthenticatedApiKey::getMetadata('endpoint'));
        $this->assertEquals('custom_value', AuthenticatedApiKey::getMetadata('custom_field'));
    }

    /** @test */
    public function it_provides_quick_access_methods()
    {
        AuthenticatedApiKey::set($this->testApiKey);

        $this->assertEquals($this->testApiKey->id, AuthenticatedApiKey::getId());
        $this->assertEquals('pro', AuthenticatedApiKey::getTier());
        $this->assertEquals('Test API Key', AuthenticatedApiKey::getName());
    }

    /** @test */
    public function it_provides_tier_checking_methods()
    {
        AuthenticatedApiKey::set($this->testApiKey);

        $this->assertTrue(AuthenticatedApiKey::hasTier('pro'));
        $this->assertFalse(AuthenticatedApiKey::hasTier('basic'));
        $this->assertFalse(AuthenticatedApiKey::hasTier('premium'));

        $this->assertTrue(AuthenticatedApiKey::isPro());
        $this->assertFalse(AuthenticatedApiKey::isBasic());
        $this->assertFalse(AuthenticatedApiKey::isPremium());
    }

    /** @test */
    public function it_provides_usage_statistics()
    {
        AuthenticatedApiKey::set($this->testApiKey);

        $usageStats = AuthenticatedApiKey::getUsageStats();

        $this->assertIsArray($usageStats);
        $this->assertEquals(25, $usageStats['daily_usage']);
        $this->assertEquals(500, $usageStats['daily_limit']);
        $this->assertEquals(475, $usageStats['daily_remaining']);
        $this->assertEquals(150, $usageStats['monthly_usage']);
        $this->assertEquals(10000, $usageStats['monthly_limit']);
        $this->assertEquals(9850, $usageStats['monthly_remaining']);
        $this->assertEquals(1500, $usageStats['total_usage']);
    }

    /** @test */
    public function it_tracks_set_duration()
    {
        $this->assertNull(AuthenticatedApiKey::getSetDuration());

        AuthenticatedApiKey::set($this->testApiKey);

        // Small delay to ensure duration is measurable
        usleep(1000); // 1ms

        $duration = AuthenticatedApiKey::getSetDuration();
        $this->assertIsFloat($duration);
        $this->assertGreaterThan(0, $duration);
    }

    /** @test */
    public function it_generates_unique_request_ids()
    {
        AuthenticatedApiKey::set($this->testApiKey);
        $requestId1 = AuthenticatedApiKey::getRequestId();

        AuthenticatedApiKey::clear();
        AuthenticatedApiKey::set($this->testApiKey);
        $requestId2 = AuthenticatedApiKey::getRequestId();

        $this->assertNotEquals($requestId1, $requestId2);
        $this->assertStringStartsWith('req_', $requestId1);
        $this->assertStringStartsWith('req_', $requestId2);
    }

    /** @test */
    public function it_can_clear_api_key()
    {
        AuthenticatedApiKey::set($this->testApiKey);
        $this->assertTrue(AuthenticatedApiKey::has());

        AuthenticatedApiKey::clear();

        $this->assertFalse(AuthenticatedApiKey::has());
        $this->assertNull(AuthenticatedApiKey::get());
        $this->assertNull(AuthenticatedApiKey::getId());
        $this->assertNull(AuthenticatedApiKey::getTier());
        $this->assertNull(AuthenticatedApiKey::getRequestId());
        $this->assertEmpty(AuthenticatedApiKey::getMetadata());
    }

    /** @test */
    public function it_provides_debug_information()
    {
        $this->assertFalse(AuthenticatedApiKey::getDebugInfo()['has_api_key']);

        AuthenticatedApiKey::set($this->testApiKey, [
            'authentication_method' => 'bearer_token',
        ]);

        $debugInfo = AuthenticatedApiKey::getDebugInfo();

        $this->assertTrue($debugInfo['has_api_key']);
        $this->assertEquals($this->testApiKey->id, $debugInfo['api_key_id']);
        $this->assertEquals('pro', $debugInfo['tier']);
        $this->assertIsString($debugInfo['request_id']);
        $this->assertIsFloat($debugInfo['set_at']);
        $this->assertIsFloat($debugInfo['set_duration_ms']);
        $this->assertIsArray($debugInfo['metadata']);
        $this->assertEquals('bearer_token', $debugInfo['metadata']['authentication_method']);
    }

    /** @test */
    public function it_can_convert_to_array()
    {
        $this->assertNull(AuthenticatedApiKey::toArray());

        AuthenticatedApiKey::set($this->testApiKey, [
            'authentication_method' => 'api_key_header',
        ]);

        $array = AuthenticatedApiKey::toArray();

        $this->assertIsArray($array);
        $this->assertEquals($this->testApiKey->id, $array['id']);
        $this->assertEquals('Test API Key', $array['name']);
        $this->assertEquals('pro', $array['tier']);
        $this->assertEquals('active', $array['status']);
        $this->assertIsArray($array['usage_stats']);
        $this->assertIsArray($array['metadata']);
        $this->assertEquals('api_key_header', $array['metadata']['authentication_method']);
    }

    /** @test */
    public function it_handles_null_values_gracefully()
    {
        $this->assertNull(AuthenticatedApiKey::getId());
        $this->assertNull(AuthenticatedApiKey::getTier());
        $this->assertNull(AuthenticatedApiKey::getName());
        $this->assertNull(AuthenticatedApiKey::getAuthenticationMethod());
        $this->assertNull(AuthenticatedApiKey::getIpAddress());
        $this->assertNull(AuthenticatedApiKey::getUserAgent());
        $this->assertNull(AuthenticatedApiKey::getAuthenticatedAt());
        $this->assertNull(AuthenticatedApiKey::getRequestId());
        $this->assertNull(AuthenticatedApiKey::getSetDuration());
        $this->assertEmpty(AuthenticatedApiKey::getUsageStats());
        $this->assertEmpty(AuthenticatedApiKey::getMetadata());

        $this->assertFalse(AuthenticatedApiKey::hasTier('any'));
        $this->assertFalse(AuthenticatedApiKey::isBasic());
        $this->assertFalse(AuthenticatedApiKey::isPro());
        $this->assertFalse(AuthenticatedApiKey::isPremium());
    }

    /** @test */
    public function it_includes_default_metadata()
    {
        // Mock request data
        $this->app->instance('request', new \Illuminate\Http\Request);

        AuthenticatedApiKey::set($this->testApiKey);

        $metadata = AuthenticatedApiKey::getMetadata();

        $this->assertArrayHasKey('authenticated_at', $metadata);
        $this->assertArrayHasKey('authentication_method', $metadata);
        $this->assertArrayHasKey('ip_address', $metadata);
        $this->assertArrayHasKey('user_agent', $metadata);
    }

    /** @test */
    public function it_prevents_instantiation()
    {
        $this->expectException(\Error::class);
        new AuthenticatedApiKey;
    }

    /** @test */
    public function it_prevents_cloning()
    {
        $reflection = new \ReflectionClass(AuthenticatedApiKey::class);
        $cloneMethod = $reflection->getMethod('__clone');

        $this->assertTrue($cloneMethod->isPrivate());
    }

    /** @test */
    public function it_prevents_unserialization()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot unserialize singleton');

        $instance = new class extends AuthenticatedApiKey
        {
            public function test_wakeup()
            {
                $this->__wakeup();
            }
        };

        $instance->testWakeup();
    }

    /** @test */
    public function it_maintains_state_across_multiple_calls()
    {
        AuthenticatedApiKey::set($this->testApiKey, [
            'authentication_method' => 'bearer_token',
            'custom_data' => 'test_value',
        ]);

        // Multiple calls should return the same data
        $this->assertEquals($this->testApiKey->id, AuthenticatedApiKey::getId());
        $this->assertEquals($this->testApiKey->id, AuthenticatedApiKey::getId());

        $this->assertEquals('pro', AuthenticatedApiKey::getTier());
        $this->assertEquals('pro', AuthenticatedApiKey::getTier());

        $this->assertEquals('bearer_token', AuthenticatedApiKey::getAuthenticationMethod());
        $this->assertEquals('test_value', AuthenticatedApiKey::getMetadata('custom_data'));
    }
}
