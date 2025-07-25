<?php

namespace Tests\Unit;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\ApiKeyCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Test suite for API key cache service.
 */
class ApiKeyCacheServiceTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyCacheService $cacheService;

    private ApiKey $testApiKey;

    private string $testKeyHash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheService = new ApiKeyCacheService;

        // Create test API key
        $this->testApiKey = ApiKey::create([
            'name' => 'Test API Key',
            'key_hash' => 'test_hash_123',
            'tier' => 'basic',
            'status' => ApiKeyStatus::ACTIVE,
            'daily_limit' => 100,
            'monthly_limit' => 1000,
            'daily_usage' => 10,
            'monthly_usage' => 50,
            'total_usage' => 500,
            'allowed_platforms' => ['youtube', 'tiktok'],
            'allowed_qualities' => ['720p', '1080p'],
            'allowed_formats' => ['mp4', 'mp3'],
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ]);

        $this->testKeyHash = 'test_hash_123';

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function it_can_cache_api_key()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        $this->assertTrue($this->cacheService->isCached($this->testKeyHash));
    }

    /** @test */
    public function it_can_retrieve_cached_api_key()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        $cachedApiKey = $this->cacheService->getCachedApiKey($this->testKeyHash);

        $this->assertInstanceOf(ApiKey::class, $cachedApiKey);
        $this->assertEquals($this->testApiKey->id, $cachedApiKey->id);
        $this->assertEquals($this->testApiKey->name, $cachedApiKey->name);
        $this->assertEquals($this->testApiKey->tier, $cachedApiKey->tier);
        $this->assertEquals($this->testApiKey->daily_usage, $cachedApiKey->daily_usage);
    }

    /** @test */
    public function it_returns_null_for_non_cached_key()
    {
        $result = $this->cacheService->getCachedApiKey('non_existent_hash');

        $this->assertNull($result);
    }

    /** @test */
    public function it_can_cache_invalid_api_key()
    {
        $invalidHash = 'invalid_hash_456';

        $this->cacheService->cacheInvalidApiKey($invalidHash);

        $result = $this->cacheService->getCachedApiKey($invalidHash);
        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_invalidate_cached_api_key()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);
        $this->assertTrue($this->cacheService->isCached($this->testKeyHash));

        $this->cacheService->invalidateApiKey($this->testKeyHash);

        $this->assertFalse($this->cacheService->isCached($this->testKeyHash));
    }

    /** @test */
    public function it_can_update_cached_usage_statistics()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        $newUsageData = [
            'daily_usage' => 15,
            'monthly_usage' => 60,
            'total_usage' => 510,
        ];

        $this->cacheService->updateCachedUsage($this->testKeyHash, $newUsageData);

        $cachedApiKey = $this->cacheService->getCachedApiKey($this->testKeyHash);

        $this->assertEquals(15, $cachedApiKey->daily_usage);
        $this->assertEquals(60, $cachedApiKey->monthly_usage);
        $this->assertEquals(510, $cachedApiKey->total_usage);
    }

    /** @test */
    public function it_does_not_cache_sensitive_data()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        // Get the raw cached data
        $cacheKey = 'api_key:'.$this->testKeyHash;
        $rawCachedData = Cache::get($cacheKey);

        // Ensure sensitive fields are not cached
        $this->assertIsArray($rawCachedData);
        $this->assertArrayNotHasKey('key_hash', $rawCachedData);
        $this->assertArrayNotHasKey('created_at', $rawCachedData);
        $this->assertArrayNotHasKey('updated_at', $rawCachedData);

        // Ensure non-sensitive fields are cached
        $this->assertArrayHasKey('id', $rawCachedData);
        $this->assertArrayHasKey('name', $rawCachedData);
        $this->assertArrayHasKey('tier', $rawCachedData);
        $this->assertArrayHasKey('daily_usage', $rawCachedData);
    }

    /** @test */
    public function it_can_warm_up_cache()
    {
        $keyHashes = [$this->testKeyHash, 'non_existent_hash'];

        $this->cacheService->warmUpCache($keyHashes);

        // Existing key should be cached
        $this->assertTrue($this->cacheService->isCached($this->testKeyHash));
        $cachedKey = $this->cacheService->getCachedApiKey($this->testKeyHash);
        $this->assertInstanceOf(ApiKey::class, $cachedKey);

        // Non-existent key should be cached as invalid
        $this->assertTrue($this->cacheService->isCached('non_existent_hash'));
        $cachedInvalid = $this->cacheService->getCachedApiKey('non_existent_hash');
        $this->assertFalse($cachedInvalid);
    }

    /** @test */
    public function it_provides_cache_statistics()
    {
        $stats = $this->cacheService->getCacheStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cache_prefix', $stats);
        $this->assertArrayHasKey('cache_ttl', $stats);
        $this->assertArrayHasKey('cache_driver', $stats);
        $this->assertEquals('api_key:', $stats['cache_prefix']);
        $this->assertEquals(300, $stats['cache_ttl']);
    }

    /** @test */
    public function it_handles_cache_ttl_correctly()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        $ttl = $this->cacheService->getCacheTtl($this->testKeyHash);

        $this->assertIsInt($ttl);
        $this->assertEquals(300, $ttl); // Default TTL
    }

    /** @test */
    public function it_returns_null_ttl_for_non_cached_key()
    {
        $ttl = $this->cacheService->getCacheTtl('non_existent_hash');

        $this->assertNull($ttl);
    }

    /** @test */
    public function cached_api_key_has_correct_properties()
    {
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);
        $cachedApiKey = $this->cacheService->getCachedApiKey($this->testKeyHash);

        // Check that the cached API key has the exists property set
        $this->assertTrue($cachedApiKey->exists);

        // Check that all expected properties are present
        $expectedProperties = [
            'id', 'name', 'tier', 'status', 'daily_limit', 'monthly_limit',
            'daily_usage', 'monthly_usage', 'total_usage', 'allowed_platforms',
            'allowed_qualities', 'allowed_formats', 'last_reset_daily', 'last_reset_monthly',
        ];

        foreach ($expectedProperties as $property) {
            $this->assertNotNull($cachedApiKey->$property, "Property {$property} should not be null");
        }
    }

    /** @test */
    public function it_handles_concurrent_cache_operations()
    {
        // Simulate concurrent caching operations
        $this->cacheService->cacheApiKey($this->testKeyHash, $this->testApiKey);

        // Update usage while key is cached
        $this->cacheService->updateCachedUsage($this->testKeyHash, ['daily_usage' => 20]);

        // Retrieve and verify
        $cachedApiKey = $this->cacheService->getCachedApiKey($this->testKeyHash);
        $this->assertEquals(20, $cachedApiKey->daily_usage);

        // Invalidate and verify
        $this->cacheService->invalidateApiKey($this->testKeyHash);
        $this->assertFalse($this->cacheService->isCached($this->testKeyHash));
    }
}
