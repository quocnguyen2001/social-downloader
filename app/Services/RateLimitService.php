<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitService
{
    /**
     * Check if the request is rate limited.
     *
     * @return array{limited: bool, seconds: int, key: string}
     */
    public function checkRateLimit(string $endpoint, Request $request, ?string $userStrategy = null): array
    {
        $config = $this->getEndpointConfig($endpoint);
        $key = $this->generateKey($config['key_prefix'], $request);

        // Apply strategy multiplier
        $maxAttempts = $this->applyStrategy($config['max_attempts'], $userStrategy);

        // Check for bypass
        if ($this->shouldBypass($request)) {
            return [
                'limited' => false,
                'seconds' => 0,
                'key' => $key,
            ];
        }

        $isLimited = RateLimiter::tooManyAttempts($key, $maxAttempts);
        $seconds = $isLimited ? RateLimiter::availableIn($key) : 0;

        return [
            'limited' => $isLimited,
            'seconds' => $seconds,
            'key' => $key,
        ];
    }

    /**
     * Hit the rate limiter for a failed attempt.
     */
    public function hit(string $key, int $decayMinutes): void
    {
        RateLimiter::hit($key, $decayMinutes * 60);
    }

    /**
     * Clear the rate limiter for successful attempts.
     */
    public function clear(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * Get configuration for a specific endpoint.
     */
    private function getEndpointConfig(string $endpoint): array
    {
        $config = config("api_rate_limits.authentication.{$endpoint}")
                 ?? config("api_rate_limits.api_endpoints.{$endpoint}")
                 ?? config('api_rate_limits.global.api_requests');

        return $config;
    }

    /**
     * Generate a rate limiting key.
     */
    private function generateKey(string $prefix, Request $request): string
    {
        $identifier = $request->user()?->id ?? $request->ip();

        return "{$prefix}:{$identifier}";
    }

    /**
     * Apply strategy multiplier to max attempts.
     */
    private function applyStrategy(int $baseAttempts, ?string $strategy): int
    {
        if (! $strategy) {
            return $baseAttempts;
        }

        $multiplier = config("api_rate_limits.strategies.{$strategy}.multiplier", 1.0);

        return (int) ceil($baseAttempts * $multiplier);
    }

    /**
     * Check if rate limiting should be bypassed.
     */
    private function shouldBypass(Request $request): bool
    {
        if (! config('api_rate_limits.bypass.enabled', false)) {
            return false;
        }

        // Check IP bypass
        $bypassIps = config('api_rate_limits.bypass.ips', []);
        if (in_array($request->ip(), $bypassIps)) {
            return true;
        }

        // Check user ID bypass
        $bypassUserIds = config('api_rate_limits.bypass.user_ids', []);
        $userId = $request->user()?->id;
        if ($userId && in_array((string) $userId, $bypassUserIds)) {
            return true;
        }

        return false;
    }

    /**
     * Get user strategy based on user type.
     */
    public function getUserStrategy(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return 'guest';
        }

        // Check if user is admin (you can customize this logic)
        if ($user->email === 'admin@example.com') { // Replace with actual admin check
            return 'admin';
        }

        // Check if user has premium membership
        if ($user->membershipPlan && $user->membershipPlan->slug === 'premium') {
            return 'premium';
        }

        return 'authenticated';
    }

    /**
     * Get rate limit message for a specific endpoint.
     */
    public function getRateLimitMessage(string $endpoint, int $seconds): string
    {
        $minutes = ceil($seconds / 60);

        $messageKey = match ($endpoint) {
            'registration' => 'registration_limit',
            'login' => 'login_limit',
            'password_reset' => 'password_reset_limit',
            default => 'too_many_requests',
        };

        $message = config("api_rate_limits.messages.{$messageKey}", 'Too many requests.');

        return str_replace([':seconds', ':minutes'], [$seconds, $minutes], $message);
    }
}
