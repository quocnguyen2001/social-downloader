<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing API key caching.
 *
 * This service provides methods to cache, retrieve, and invalidate
 * API key data to improve performance and reduce database queries.
 */
class ApiKeyCacheService
{
    /**
     * Cache TTL for API key data (in seconds).
     */
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * Cache key prefix for API keys.
     */
    private const CACHE_PREFIX = 'api_key:';

    /**
     * Cache an API key.
     *
     * @param  string  $keyHash  The hashed API key
     * @param  ApiKey  $apiKey  The API key model
     */
    public function cacheApiKey(string $keyHash, ApiKey $apiKey): void
    {
        $cacheKey = $this->getCacheKey($keyHash);

        // Cache only non-sensitive data
        $cachedData = $apiKey->only([
            'id', 'name', 'tier', 'status', 'daily_limit', 'monthly_limit',
            'daily_usage', 'monthly_usage', 'total_usage', 'allowed_platforms',
            'allowed_qualities', 'allowed_formats', 'last_reset_daily', 'last_reset_monthly',
        ]);

        Cache::put($cacheKey, $cachedData, self::CACHE_TTL);

        Log::debug('API key cached', [
            'api_key_id' => $apiKey->id,
            'cache_key' => $cacheKey,
            'ttl' => self::CACHE_TTL,
        ]);
    }

    /**
     * Get an API key from cache.
     *
     * @param  string  $keyHash  The hashed API key
     * @return ApiKey|null|false The API key model, null if not found, false if cached as invalid
     */
    public function getCachedApiKey(string $keyHash): ApiKey|null|false
    {
        $cacheKey = $this->getCacheKey($keyHash);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData === null) {
            return null; // Not in cache
        }

        if ($cachedData === false) {
            return false; // Cached as invalid
        }

        // Reconstruct API key model from cached data
        $apiKey = new ApiKey($cachedData);
        $apiKey->exists = true;

        Log::debug('API key retrieved from cache', [
            'api_key_id' => $apiKey->id,
            'cache_key' => $cacheKey,
        ]);

        return $apiKey;
    }

    /**
     * Cache a negative result (invalid API key).
     *
     * @param  string  $keyHash  The hashed API key
     */
    public function cacheInvalidApiKey(string $keyHash): void
    {
        $cacheKey = $this->getCacheKey($keyHash);

        // Cache negative result for a shorter time
        Cache::put($cacheKey, false, 60); // 1 minute

        Log::debug('Invalid API key cached', [
            'cache_key' => $cacheKey,
            'ttl' => 60,
        ]);
    }

    /**
     * Invalidate cached API key data.
     *
     * @param  string|ApiKey  $keyHashOrModel  The hashed API key or API key model
     */
    public function invalidateApiKey(string|ApiKey $keyHashOrModel): void
    {
        if ($keyHashOrModel instanceof ApiKey) {
            // If we have the model, we need to find all possible cache keys
            // This is more complex as we don't store the original key value
            // For now, we'll just log that invalidation was requested
            Log::info('API key cache invalidation requested', [
                'api_key_id' => $keyHashOrModel->id,
                'note' => 'Manual cache invalidation may be needed',
            ]);

            return;
        }

        $cacheKey = $this->getCacheKey($keyHashOrModel);
        Cache::forget($cacheKey);

        Log::info('API key cache invalidated', [
            'cache_key' => $cacheKey,
        ]);
    }

    /**
     * Clear all API key cache entries.
     */
    public function clearAllApiKeyCache(): void
    {
        // This is a brute force approach - in production you might want to use cache tags
        $pattern = self::CACHE_PREFIX.'*';

        // Note: This method depends on your cache driver
        // For Redis, you could use SCAN with pattern matching
        // For now, we'll just log the request
        Log::info('API key cache clear requested', [
            'pattern' => $pattern,
            'note' => 'Manual cache clearing may be needed depending on cache driver',
        ]);
    }

    /**
     * Update cached API key usage statistics.
     *
     * @param  string  $keyHash  The hashed API key
     * @param  array  $usageData  Updated usage data
     */
    public function updateCachedUsage(string $keyHash, array $usageData): void
    {
        $cacheKey = $this->getCacheKey($keyHash);
        $cachedData = Cache::get($cacheKey);

        if ($cachedData && is_array($cachedData)) {
            // Update usage statistics in cache
            $cachedData = array_merge($cachedData, $usageData);
            Cache::put($cacheKey, $cachedData, self::CACHE_TTL);

            Log::debug('API key usage updated in cache', [
                'cache_key' => $cacheKey,
                'updated_fields' => array_keys($usageData),
            ]);
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array Cache statistics
     */
    public function getCacheStatistics(): array
    {
        // This would need to be implemented based on your cache driver
        // For now, return basic info
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::CACHE_TTL,
            'cache_driver' => config('cache.default'),
        ];
    }

    /**
     * Warm up the cache for frequently used API keys.
     *
     * @param  array  $apiKeyHashes  Array of API key hashes to warm up
     */
    public function warmUpCache(array $apiKeyHashes): void
    {
        foreach ($apiKeyHashes as $keyHash) {
            $cachedKey = $this->getCachedApiKey($keyHash);

            if ($cachedKey === null) {
                // Not in cache, load from database
                $apiKey = ApiKey::where('key_hash', $keyHash)
                    ->where('status', \App\Enums\ApiKeyStatus::ACTIVE)
                    ->first();

                if ($apiKey) {
                    $this->cacheApiKey($keyHash, $apiKey);
                } else {
                    $this->cacheInvalidApiKey($keyHash);
                }
            }
        }

        Log::info('API key cache warmed up', [
            'keys_processed' => count($apiKeyHashes),
        ]);
    }

    /**
     * Get the cache key for an API key hash.
     *
     * @param  string  $keyHash  The hashed API key
     * @return string The cache key
     */
    private function getCacheKey(string $keyHash): string
    {
        return self::CACHE_PREFIX.$keyHash;
    }

    /**
     * Check if an API key is cached.
     *
     * @param  string  $keyHash  The hashed API key
     * @return bool True if the key is cached
     */
    public function isCached(string $keyHash): bool
    {
        $cacheKey = $this->getCacheKey($keyHash);

        return Cache::has($cacheKey);
    }

    /**
     * Get the remaining TTL for a cached API key.
     *
     * @param  string  $keyHash  The hashed API key
     * @return int|null The remaining TTL in seconds, or null if not cached
     */
    public function getCacheTtl(string $keyHash): ?int
    {
        $cacheKey = $this->getCacheKey($keyHash);

        // Note: TTL retrieval depends on cache driver
        // This is a simplified implementation
        if (Cache::has($cacheKey)) {
            return self::CACHE_TTL; // Return default TTL
        }

        return null;
    }
}
