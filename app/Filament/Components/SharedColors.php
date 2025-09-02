<?php

namespace App\Filament\Components;

class SharedColors
{
    /**
     * Get color configuration from config file.
     */
    private static function getConfig(string $key): array
    {
        return config("filament-colors.{$key}", []);
    }

    /**
     * Get API key status colors.
     */
    public static function apiKeyStatus(): array
    {
        return self::getConfig('status.api_key');
    }

    /**
     * Get download session status colors.
     */
    public static function downloadSessionStatus(): array
    {
        return self::getConfig('status.download_session');
    }

    /**
     * Get transaction status colors.
     */
    public static function transactionStatus(): array
    {
        return self::getConfig('status.transaction');
    }

    /**
     * Get order status colors.
     */
    public static function orderStatus(): array
    {
        return self::getConfig('status.order');
    }

    /**
     * Get platform colors.
     */
    public static function platform(): array
    {
        return self::getConfig('platform');
    }

    /**
     * Get platform icons.
     */
    public static function platformIcons(): array
    {
        return self::getConfig('platform_icons');
    }

    /**
     * Get HTTP status colors.
     */
    public static function httpStatus(): array
    {
        return self::getConfig('http_status');
    }

    /**
     * Get membership plan billing cycle colors.
     */
    public static function membershipPlanBillingCycle(): array
    {
        return self::getConfig('membership_plan.billing_cycle');
    }

    /**
     * Get membership plan type colors.
     */
    public static function membershipPlanType(): array
    {
        return self::getConfig('membership_plan.plan_type');
    }

    /**
     * Get usage level colors.
     */
    public static function usageLevels(): array
    {
        return self::getConfig('usage_levels');
    }

    /**
     * Get token count colors.
     */
    public static function tokenCount(): array
    {
        return self::getConfig('token_count');
    }

    /**
     * Get usage level color based on percentage.
     */
    public static function getUsageLevelColor(float $percentage): string
    {
        $levels = self::usageLevels();

        if ($percentage >= 80) {
            return $levels['high'];
        } elseif ($percentage >= 60) {
            return $levels['medium'];
        }

        return $levels['low'];
    }

    /**
     * Get token count color based on count.
     */
    public static function getTokenCountColor(int $count): string
    {
        $levels = self::tokenCount();

        if ($count === 0) {
            return $levels['none'];
        } elseif ($count <= 2) {
            return $levels['low'];
        } elseif ($count <= 5) {
            return $levels['medium'];
        }

        return $levels['high'];
    }

    /**
     * Get platform icon for a given platform.
     */
    public static function getPlatformIcon(string $platform): string
    {
        $icons = self::platformIcons();

        return $icons[$platform] ?? $icons['default'];
    }
}
