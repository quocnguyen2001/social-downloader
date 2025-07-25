<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * API Usage Tracking Service
 *
 * Tracks and validates API usage limits for both guest users (IP-based)
 * and authenticated users (user-based) with support for different time periods.
 */
class ApiUsageTracker
{
    private const CACHE_PREFIX = 'api_usage';

    private const CACHE_TTL_HOURLY = 3600; // 1 hour

    private const CACHE_TTL_DAILY = 86400; // 24 hours

    private const CACHE_TTL_TOTAL = 2592000; // 30 days

    /**
     * Check if a guest user (identified by IP) can make a request.
     *
     * @return array{allowed: bool, current_usage: array, limits: array, reset_times: array}
     */
    public function checkGuestLimits(string $ipAddress, array $limits): array
    {
        $hourlyKey = $this->getGuestCacheKey($ipAddress, 'hourly');
        $dailyKey = $this->getGuestCacheKey($ipAddress, 'daily');

        $hourlyUsage = $this->getCurrentUsage($hourlyKey);
        $dailyUsage = $this->getCurrentUsage($dailyKey);

        $hourlyLimit = $limits['hourly_request_limit'] ?? 0;
        $dailyLimit = $limits['daily_request_limit'] ?? 0;

        $hourlyAllowed = $hourlyLimit === 0 || $hourlyUsage < $hourlyLimit;
        $dailyAllowed = $dailyLimit === 0 || $dailyUsage < $dailyLimit;

        return [
            'allowed' => $hourlyAllowed && $dailyAllowed,
            'current_usage' => [
                'hourly' => $hourlyUsage,
                'daily' => $dailyUsage,
            ],
            'limits' => [
                'hourly' => $hourlyLimit,
                'daily' => $dailyLimit,
            ],
            'reset_times' => [
                'hourly' => $this->getNextHourlyReset(),
                'daily' => $this->getNextDailyReset(),
            ],
        ];
    }

    /**
     * Check if an authenticated user can make a request.
     *
     * @return array{allowed: bool, current_usage: array, limits: array, reset_times: array}
     */
    public function checkUserLimits(User $user, array $limits): array
    {
        $hourlyKey = $this->getUserCacheKey($user->id, 'hourly');
        $dailyKey = $this->getUserCacheKey($user->id, 'daily');
        $totalKey = $this->getUserCacheKey($user->id, 'total');

        $hourlyUsage = $this->getCurrentUsage($hourlyKey);
        $dailyUsage = $this->getCurrentUsage($dailyKey);
        $totalUsage = $this->getCurrentUsage($totalKey);

        $hourlyLimit = $limits['hourly_request_limit'] ?? 0;
        $dailyLimit = $limits['daily_request_limit'] ?? 0;
        $totalLimit = $limits['total_request_download'] ?? 0;

        $hourlyAllowed = $hourlyLimit === 0 || $hourlyUsage < $hourlyLimit;
        $dailyAllowed = $dailyLimit === 0 || $dailyUsage < $dailyLimit;
        $totalAllowed = $totalLimit === 0 || $totalUsage < $totalLimit;

        return [
            'allowed' => $hourlyAllowed && $dailyAllowed && $totalAllowed,
            'current_usage' => [
                'hourly' => $hourlyUsage,
                'daily' => $dailyUsage,
                'total' => $totalUsage,
            ],
            'limits' => [
                'hourly' => $hourlyLimit,
                'daily' => $dailyLimit,
                'total' => $totalLimit,
            ],
            'reset_times' => [
                'hourly' => $this->getNextHourlyReset(),
                'daily' => $this->getNextDailyReset(),
                'total' => null, // Total limits don't reset
            ],
        ];
    }

    /**
     * Increment usage for a guest user.
     */
    public function incrementGuestUsage(string $ipAddress): void
    {
        $hourlyKey = $this->getGuestCacheKey($ipAddress, 'hourly');
        $dailyKey = $this->getGuestCacheKey($ipAddress, 'daily');

        $this->incrementUsage($hourlyKey, self::CACHE_TTL_HOURLY);
        $this->incrementUsage($dailyKey, self::CACHE_TTL_DAILY);

        Log::debug('Guest usage incremented', [
            'ip_address' => $ipAddress,
            'hourly_usage' => $this->getCurrentUsage($hourlyKey),
            'daily_usage' => $this->getCurrentUsage($dailyKey),
        ]);
    }

    /**
     * Increment usage for an authenticated user.
     */
    public function incrementUserUsage(User $user): void
    {
        $hourlyKey = $this->getUserCacheKey($user->id, 'hourly');
        $dailyKey = $this->getUserCacheKey($user->id, 'daily');
        $totalKey = $this->getUserCacheKey($user->id, 'total');

        $this->incrementUsage($hourlyKey, self::CACHE_TTL_HOURLY);
        $this->incrementUsage($dailyKey, self::CACHE_TTL_DAILY);
        $this->incrementUsage($totalKey, self::CACHE_TTL_TOTAL);

        Log::debug('User usage incremented', [
            'user_id' => $user->id,
            'hourly_usage' => $this->getCurrentUsage($hourlyKey),
            'daily_usage' => $this->getCurrentUsage($dailyKey),
            'total_usage' => $this->getCurrentUsage($totalKey),
        ]);
    }

    /**
     * Get current usage for a cache key.
     */
    private function getCurrentUsage(string $key): int
    {
        return (int) Cache::get($key, 0);
    }

    /**
     * Increment usage with atomic operation.
     */
    private function incrementUsage(string $key, int $ttl): void
    {
        try {
            // Use Redis for atomic increment if available
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->getRedis();
                $redis->multi();
                $redis->incr($key);
                $redis->expire($key, $ttl);
                $redis->exec();
            } else {
                // Fallback to cache increment
                $current = $this->getCurrentUsage($key);
                Cache::put($key, $current + 1, $ttl);
            }
        } catch (\Exception $e) {
            Log::error('Failed to increment usage', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            // Fallback to simple cache increment
            $current = $this->getCurrentUsage($key);
            Cache::put($key, $current + 1, $ttl);
        }
    }

    /**
     * Generate cache key for guest usage.
     */
    private function getGuestCacheKey(string $ipAddress, string $period): string
    {
        $hashedIp = hash('sha256', $ipAddress);
        $timeKey = $this->getTimeKey($period);

        return sprintf('%s:guest:%s:%s:%s', self::CACHE_PREFIX, $hashedIp, $period, $timeKey);
    }

    /**
     * Generate cache key for user usage.
     */
    private function getUserCacheKey(int $userId, string $period): string
    {
        $timeKey = $this->getTimeKey($period);

        return sprintf('%s:user:%d:%s:%s', self::CACHE_PREFIX, $userId, $period, $timeKey);
    }

    /**
     * Get time-based key component for cache keys.
     */
    private function getTimeKey(string $period): string
    {
        $now = Carbon::now();

        return match ($period) {
            'hourly' => $now->format('Y-m-d-H'),
            'daily' => $now->format('Y-m-d'),
            'total' => 'lifetime',
            default => $now->format('Y-m-d'),
        };
    }

    /**
     * Get next hourly reset time.
     */
    private function getNextHourlyReset(): Carbon
    {
        return Carbon::now()->addHour()->startOfHour();
    }

    /**
     * Get next daily reset time.
     */
    private function getNextDailyReset(): Carbon
    {
        return Carbon::now()->addDay()->startOfDay();
    }

    /**
     * Clear usage data for a user (useful for testing or admin actions).
     *
     * @param  User|null  $user  If null, clears guest data for given IP
     * @param  string|null  $ipAddress  Required if user is null
     */
    public function clearUsage(?User $user = null, ?string $ipAddress = null): void
    {
        if ($user) {
            $patterns = [
                $this->getUserCacheKey($user->id, 'hourly'),
                $this->getUserCacheKey($user->id, 'daily'),
                $this->getUserCacheKey($user->id, 'total'),
            ];
        } elseif ($ipAddress) {
            $patterns = [
                $this->getGuestCacheKey($ipAddress, 'hourly'),
                $this->getGuestCacheKey($ipAddress, 'daily'),
            ];
        } else {
            throw new \InvalidArgumentException('Either user or ipAddress must be provided');
        }

        foreach ($patterns as $key) {
            Cache::forget($key);
        }
    }
}
