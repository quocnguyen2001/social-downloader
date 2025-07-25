<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Log;

/**
 * Singleton service for managing the authenticated API key throughout the request lifecycle.
 *
 * This service provides a centralized way to store and retrieve the authenticated
 * API key after it passes through the authentication middleware, making it accessible
 * throughout the entire request without additional database queries.
 */
class AuthenticatedApiKey
{
    /**
     * The authenticated API key instance.
     */
    private static ?ApiKey $apiKey = null;

    /**
     * Additional metadata about the authentication.
     */
    private static array $metadata = [];

    /**
     * Timestamp when the API key was set.
     */
    private static ?float $setAt = null;

    /**
     * Request ID for debugging purposes.
     */
    private static ?string $requestId = null;

    /**
     * Set the authenticated API key for the current request.
     *
     * @param  ApiKey  $apiKey  The authenticated API key
     * @param  array  $metadata  Additional metadata about the authentication
     */
    public static function set(ApiKey $apiKey, array $metadata = []): void
    {
        self::$apiKey = $apiKey;
        self::$metadata = array_merge([
            'authenticated_at' => now()->toISOString(),
            'authentication_method' => 'unknown',
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ], $metadata);
        self::$setAt = microtime(true);
        self::$requestId = self::generateRequestId();

        Log::debug('Authenticated API key set in singleton', [
            'api_key_id' => $apiKey->id,
            'tier' => $apiKey->tier,
            'request_id' => self::$requestId,
            'metadata' => self::$metadata,
        ]);
    }

    /**
     * Get the authenticated API key for the current request.
     *
     * @return ApiKey|null The authenticated API key or null if not set
     */
    public static function get(): ?ApiKey
    {
        if (self::$apiKey) {
            Log::debug('Authenticated API key retrieved from singleton', [
                'api_key_id' => self::$apiKey->id,
                'request_id' => self::$requestId,
                'set_duration_ms' => self::$setAt ? round((microtime(true) - self::$setAt) * 1000, 2) : null,
            ]);
        }

        return self::$apiKey;
    }

    /**
     * Check if an API key is currently set.
     *
     * @return bool True if an API key is set
     */
    public static function has(): bool
    {
        return self::$apiKey !== null;
    }

    /**
     * Get the API key ID without loading the full model.
     *
     * @return string|null The API key ID or null if not set
     */
    public static function getId(): ?string
    {
        return self::$apiKey?->id;
    }

    /**
     * Get the API key tier.
     *
     * @return string|null The API key tier or null if not set
     */
    public static function getTier(): ?string
    {
        return self::$apiKey?->tier;
    }

    /**
     * Get the API key name.
     *
     * @return string|null The API key name or null if not set
     */
    public static function getName(): ?string
    {
        return self::$apiKey?->name;
    }

    /**
     * Get authentication metadata.
     *
     * @param  string|null  $key  Specific metadata key to retrieve
     * @return mixed The metadata value or all metadata if no key specified
     */
    public static function getMetadata(?string $key = null): mixed
    {
        if ($key !== null) {
            return self::$metadata[$key] ?? null;
        }

        return self::$metadata;
    }

    /**
     * Get the authentication method used.
     *
     * @return string|null The authentication method
     */
    public static function getAuthenticationMethod(): ?string
    {
        return self::$metadata['authentication_method'] ?? null;
    }

    /**
     * Get the IP address of the authenticated request.
     *
     * @return string|null The IP address
     */
    public static function getIpAddress(): ?string
    {
        return self::$metadata['ip_address'] ?? null;
    }

    /**
     * Get the user agent of the authenticated request.
     *
     * @return string|null The user agent
     */
    public static function getUserAgent(): ?string
    {
        return self::$metadata['user_agent'] ?? null;
    }

    /**
     * Get the timestamp when the API key was authenticated.
     *
     * @return string|null The authentication timestamp
     */
    public static function getAuthenticatedAt(): ?string
    {
        return self::$metadata['authenticated_at'] ?? null;
    }

    /**
     * Get the request ID for debugging.
     *
     * @return string|null The request ID
     */
    public static function getRequestId(): ?string
    {
        return self::$requestId;
    }

    /**
     * Get the duration since the API key was set (in milliseconds).
     *
     * @return float|null The duration in milliseconds or null if not set
     */
    public static function getSetDuration(): ?float
    {
        if (self::$setAt === null) {
            return null;
        }

        return round((microtime(true) - self::$setAt) * 1000, 2);
    }

    /**
     * Check if the authenticated API key has a specific tier.
     *
     * @param  string  $tier  The tier to check
     * @return bool True if the API key has the specified tier
     */
    public static function hasTier(string $tier): bool
    {
        return self::$apiKey?->tier === $tier;
    }

    /**
     * Check if the authenticated API key is premium tier.
     *
     * @return bool True if the API key is premium tier
     */
    public static function isPremium(): bool
    {
        return self::hasTier('premium');
    }

    /**
     * Check if the authenticated API key is pro tier.
     *
     * @return bool True if the API key is pro tier
     */
    public static function isPro(): bool
    {
        return self::hasTier('pro');
    }

    /**
     * Check if the authenticated API key is basic tier.
     *
     * @return bool True if the API key is basic tier
     */
    public static function isBasic(): bool
    {
        return self::hasTier('basic');
    }

    /**
     * Get usage statistics for the authenticated API key.
     *
     * @return array Usage statistics
     */
    public static function getUsageStats(): array
    {
        if (! self::$apiKey) {
            return [];
        }

        return [
            'daily_usage' => self::$apiKey->daily_usage,
            'daily_limit' => self::$apiKey->daily_limit,
            'daily_remaining' => max(0, self::$apiKey->daily_limit - self::$apiKey->daily_usage),
            'monthly_usage' => self::$apiKey->monthly_usage,
            'monthly_limit' => self::$apiKey->monthly_limit,
            'monthly_remaining' => max(0, self::$apiKey->monthly_limit - self::$apiKey->monthly_usage),
            'total_usage' => self::$apiKey->total_usage,
        ];
    }

    /**
     * Clear the authenticated API key (typically called at the end of request).
     */
    public static function clear(): void
    {
        if (self::$apiKey) {
            Log::debug('Authenticated API key cleared from singleton', [
                'api_key_id' => self::$apiKey->id,
                'request_id' => self::$requestId,
                'total_duration_ms' => self::getSetDuration(),
            ]);
        }

        self::$apiKey = null;
        self::$metadata = [];
        self::$setAt = null;
        self::$requestId = null;
    }

    /**
     * Get debug information about the current state.
     *
     * @return array Debug information
     */
    public static function getDebugInfo(): array
    {
        return [
            'has_api_key' => self::has(),
            'api_key_id' => self::getId(),
            'tier' => self::getTier(),
            'request_id' => self::$requestId,
            'set_at' => self::$setAt,
            'set_duration_ms' => self::getSetDuration(),
            'metadata' => self::$metadata,
        ];
    }

    /**
     * Convert the authenticated API key to array format.
     *
     * @return array|null Array representation or null if not set
     */
    public static function toArray(): ?array
    {
        if (! self::$apiKey) {
            return null;
        }

        return [
            'id' => self::$apiKey->id,
            'name' => self::$apiKey->name,
            'tier' => self::$apiKey->tier,
            'status' => self::$apiKey->status->value,
            'usage_stats' => self::getUsageStats(),
            'metadata' => self::$metadata,
        ];
    }

    /**
     * Generate a unique request ID for debugging.
     *
     * @return string The request ID
     */
    private static function generateRequestId(): string
    {
        return 'req_'.uniqid().'_'.substr(md5(microtime(true)), 0, 8);
    }

    /**
     * Prevent instantiation of this singleton class.
     */
    private function __construct() {}

    /**
     * Prevent cloning of this singleton class.
     */
    private function __clone() {}

    /**
     * Prevent unserialization of this singleton class.
     */
    public function __wakeup()
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
