<?php

declare(strict_types=1);

use App\Models\User;
use App\Settings\AuthenticatedApiLimitsSettings;
use App\Settings\GuestApiLimitsSettings;
use Illuminate\Support\Facades\Cache;

if (! function_exists('guestApiLimits')) {
    /**
     * Get guest API limits settings with caching.
     */
    function guestApiLimits(): GuestApiLimitsSettings
    {
        return Cache::remember(
            'api_limits_guest',
            now()->addMinutes(10),
            fn () => app(GuestApiLimitsSettings::class)
        );
    }
}

if (! function_exists('authenticatedApiLimits')) {
    /**
     * Get authenticated API limits settings with caching.
     */
    function authenticatedApiLimits(): AuthenticatedApiLimitsSettings
    {
        return Cache::remember(
            'api_limits_authenticated',
            now()->addMinutes(10),
            fn () => app(AuthenticatedApiLimitsSettings::class)
        );
    }
}

if (! function_exists('getUserApiLimits')) {
    /**
     * Get API limits for a specific user based on their membership plan.
     *
     * Returns membership plan limits if user has an active plan,
     * otherwise returns default authenticated user limits.
     *
     * @return array<string, mixed>
     */
    function getUserApiLimits(User $user): array
    {
        $cacheKey = "api_limits_user_{$user->id}";

        return Cache::remember(
            $cacheKey,
            now()->addMinutes(5), // Shorter cache for user-specific data
            function () use ($user): array {
                // Check if user has an active membership plan
                if ($user->membershipPlan && $user->hasMembershipActive()) {
                    $plan = $user->membershipPlan;

                    return [
                        'daily_request_limit' => $plan->daily_request_limit,
                        'weekly_request_limit' => $plan->weekly_request_limit,
                        'total_request_download' => $plan->total_request_download,
                        'allowed_platforms' => $plan->allowed_platforms ?? [],
                        'allowed_qualities' => $plan->allowed_qualities ?? [],
                        'allowed_formats' => $plan->allowed_formats ?? [],
                        'priority_processing' => $plan->priority_processing,
                        'source' => 'membership_plan',
                        'plan_name' => $plan->name,
                    ];
                }

                // Fallback to default authenticated user limits
                $settings = authenticatedApiLimits();

                return [
                    'daily_request_limit' => $settings->daily_request_limit,
                    'hourly_request_limit' => $settings->hourly_request_limit,
                    'allowed_platforms' => $settings->allowed_platforms,
                    'allowed_qualities' => $settings->allowed_qualities,
                    'allowed_formats' => $settings->allowed_formats,
                    'rate_limit_per_minute' => $settings->rate_limit_per_minute,
                    'priority_processing' => false,
                    'source' => 'authenticated_defaults',
                    'plan_name' => null,
                ];
            }
        );
    }
}

if (! function_exists('clearApiLimitsCache')) {
    /**
     * Clear API limits cache for all or specific user.
     *
     * @param  User|null  $user  If provided, clears cache only for this user
     */
    function clearApiLimitsCache(?User $user = null): void
    {
        if ($user) {
            Cache::forget("api_limits_user_{$user->id}");
        } else {
            // Clear all API limits cache
            Cache::forget('api_limits_guest');
            Cache::forget('api_limits_authenticated');

            // Clear all user-specific caches (this is expensive, use sparingly)
            $pattern = 'api_limits_user_*';
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getStore()->getRedis();
                $keys = $redis->keys($pattern);
                if (! empty($keys)) {
                    $redis->del($keys);
                }
            }
        }
    }
}

if (! function_exists('getApiLimitsCacheInfo')) {
    /**
     * Get cache information for API limits (useful for debugging).
     *
     * @return array<string, mixed>
     */
    function getApiLimitsCacheInfo(?User $user = null): array
    {
        $info = [
            'guest_cached' => Cache::has('api_limits_guest'),
            'authenticated_cached' => Cache::has('api_limits_authenticated'),
        ];

        if ($user) {
            $userCacheKey = "api_limits_user_{$user->id}";
            $info['user_cached'] = Cache::has($userCacheKey);
            $info['user_cache_key'] = $userCacheKey;
        }

        return $info;
    }
}

if (! function_exists('format_currency')) {
    function format_currency(float $amount, string $currency = 'VND'): string
    {
        if (is_nan($amount) || is_infinite($amount)) {
            return '0';
        }

        $cacheKey = "currency_formatter_{$currency}";

        try {
            $formatter = Cache::remember(
                $cacheKey,
                now()->addHours(24),
                function () use ($currency): NumberFormatter {
                    $locale = match ($currency) {
                        'VND' => 'vi_VN',
                        'EUR' => 'de_DE',
                        'GBP' => 'en_GB',
                        'JPY' => 'ja_JP',
                        'CNY' => 'zh_CN',
                        'KRW' => 'ko_KR',
                        'THB' => 'th_TH',
                        default => 'en_US',
                    };

                    $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

                    $formatter->setTextAttribute(NumberFormatter::CURRENCY_CODE, $currency);

                    if ($currency === 'VND') {
                        $formatter->setAttribute(NumberFormatter::FRACTION_DIGITS, 0);
                    }

                    return $formatter;
                }
            );

            $formatted = $formatter->formatCurrency($amount, $currency);

            if ($formatted === false) {
                return $currency.' '.number_format($amount, $currency === 'VND' ? 0 : 2);
            }

            return $formatted;

        } catch (Exception $e) {
            $symbol = match ($currency) {
                'VND' => '₫',
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'JPY', 'CNY' => '¥',
                'KRW' => '₩',
                'THB' => '฿',
                default => $currency.' ',
            };

            $decimals = $currency === 'VND' ? 0 : 2;
            $formatted = number_format($amount, $decimals);

            return match ($currency) {
                'VND', 'EUR' => $formatted.' '.$symbol,
                default => $symbol.$formatted,
            };
        }
    }
}
