<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\ApiKeyCacheService;
use App\Services\AuthenticatedApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for API key authentication and rate limiting.
 *
 * This middleware validates API keys, implements caching for performance,
 * and provides rate limiting based on API key tiers.
 */
class ApiKeyAuthentication
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private ApiKeyCacheService $cacheService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$scopes): Response
    {
        try {
            // Extract API key from request
            $apiKeyValue = $this->extractApiKey($request);

            if (! $apiKeyValue) {
                return $this->unauthorizedResponse('API key is required');
            }

            // Validate and get API key with caching
            $apiKey = $this->validateApiKey($apiKeyValue);

            if (! $apiKey) {
                return $this->unauthorizedResponse('Invalid API key');
            }

            // Check API key status
            if ($apiKey->status !== ApiKeyStatus::ACTIVE) {
                return $this->unauthorizedResponse('API key is not active');
            }

            AuthenticatedApiKey::set($apiKey, [
                'authentication_method' => $this->getAuthenticationMethod($request),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Check rate limits
            $rateLimitResult = $this->checkRateLimit($apiKey, $request);
            if ($rateLimitResult !== true) {
                return $rateLimitResult;
            }

            // Check usage limits
            $usageLimitResult = $this->checkUsageLimits($apiKey);
            if ($usageLimitResult !== true) {
                return $usageLimitResult;
            }

            // Check scopes if provided
            if (! empty($scopes) && ! $this->hasRequiredScopes($apiKey, $scopes)) {
                return $this->forbiddenResponse('Insufficient permissions for this endpoint');
            }

            // Store API key in singleton for request lifecycle

            // Also add to request attributes for backward compatibility
            $request->attributes->set('api_key', $apiKey);

            // Log successful authentication
            Log::info('API key authenticated successfully', [
                'api_key_id' => $apiKey->id,
                'tier' => $apiKey->tier,
                'endpoint' => $request->path(),
                'ip' => $request->ip(),
                'request_id' => AuthenticatedApiKey::getRequestId(),
            ]);

            return $next($request);

        } catch (\Exception $e) {
            Log::error('API key authentication error', [
                'error' => $e->getMessage(),
                'request_path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return $this->errorResponse('Authentication error occurred');
        }
    }

    /**
     * Extract API key from request headers.
     */
    private function extractApiKey(Request $request): ?string
    {
        // Try Authorization Bearer token first
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $bearerToken;
        }

        // Try X-API-Key header
        $apiKeyHeader = $request->header('X-API-Key');
        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

        // Try api_key query parameter (less secure, for testing only)
        if (app()->environment(['local', 'testing'])) {
            return $request->query('api_key');
        }

        return null;
    }

    /**
     * Validate API key with caching.
     */
    private function validateApiKey(string $apiKeyValue): ?ApiKey
    {
        $keyHash = hash('sha256', $apiKeyValue);

        // Try to get from cache first
        $apiKey = $this->cacheService->getCachedApiKey($keyHash);

        if ($apiKey === null) {
            // Not in cache, query database
            $apiKey = ApiKey::where('key_hash', $keyHash)
                ->where('status', ApiKeyStatus::ACTIVE)
                ->first();

            if ($apiKey) {
                // Cache the API key
                $this->cacheService->cacheApiKey($keyHash, $apiKey);
            } else {
                // Cache negative result
                $this->cacheService->cacheInvalidApiKey($keyHash);
            }
        } elseif ($apiKey === false) {
            // Cached negative result
            return null;
        }

        return $apiKey;
    }

    /**
     * Check rate limits for the API key.
     */
    private function checkRateLimit(ApiKey $apiKey, Request $request): Response|bool
    {
        $user = $request->user();
        $rateLimitKey = "api_rate_limit:{$apiKey->id}";
        $maxAttempts = $this->getRateLimitForTier($user ? $user->tier : 'none');
        $decayMinutes = 1; // 1 minute window

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            Log::warning('API rate limit exceeded', [
                'api_key_id' => $apiKey->id,
                'tier' => $user ? $user->tier : null,
                'max_attempts' => $maxAttempts,
                'retry_after' => $retryAfter,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'window' => $decayMinutes * 60, // in seconds
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, $decayMinutes * 60);

        return true;
    }

    /**
     * Check usage limits for the API key.
     */
    private function checkUsageLimits(ApiKey $apiKey): Response|bool
    {
        // Check daily limit
        if ($apiKey->daily_limit > 0 && $apiKey->daily_usage >= $apiKey->daily_limit) {
            Log::warning('Daily usage limit exceeded', [
                'api_key_id' => $apiKey->id,
                'daily_usage' => $apiKey->daily_usage,
                'daily_limit' => $apiKey->daily_limit,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Daily usage limit exceeded',
                'daily_usage' => $apiKey->daily_usage,
                'daily_limit' => $apiKey->daily_limit,
                'resets_at' => now()->addDay()->startOfDay()->toISOString(),
            ], 429);
        }

        // Check monthly limit
        if ($apiKey->monthly_limit > 0 && $apiKey->monthly_usage >= $apiKey->monthly_limit) {
            Log::warning('Monthly usage limit exceeded', [
                'api_key_id' => $apiKey->id,
                'monthly_usage' => $apiKey->monthly_usage,
                'monthly_limit' => $apiKey->monthly_limit,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Monthly usage limit exceeded',
                'monthly_usage' => $apiKey->monthly_usage,
                'monthly_limit' => $apiKey->monthly_limit,
                'resets_at' => now()->addMonth()->startOfMonth()->toISOString(),
            ], 429);
        }

        return true;
    }

    /**
     * Check if API key has required scopes.
     */
    private function hasRequiredScopes(ApiKey $apiKey, array $requiredScopes): bool
    {
        // For now, we'll use a simple tier-based permission system
        // This can be extended to use actual scopes/permissions
        $tierPermissions = [
            'basic' => ['extract'],
            'pro' => ['extract', 'batch'],
            'premium' => ['extract', 'batch', 'priority', 'analytics'],
        ];

        $apiKeyScopes = $tierPermissions[$apiKey->tier] ?? [];

        foreach ($requiredScopes as $scope) {
            if (! in_array($scope, $apiKeyScopes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get rate limit for API key tier.
     */
    private function getRateLimitForTier(string $tier): int
    {
        return match ($tier) {
            'premium' => 1000, // 1000 requests per minute
            'pro' => 300,      // 300 requests per minute
            'basic' => 60,     // 60 requests per minute
            default => 60,     // 10 requests per minute for unknown tiers
        };
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }

    /**
     * Return forbidden response.
     */
    private function forbiddenResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN',
        ], 403);
    }

    /**
     * Return error response.
     */
    private function errorResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR',
        ], 500);
    }

    /**
     * Determine the authentication method used.
     */
    private function getAuthenticationMethod(Request $request): string
    {
        if ($request->bearerToken()) {
            return 'bearer_token';
        }

        if ($request->header('X-API-Key')) {
            return 'api_key_header';
        }

        if (app()->environment(['local', 'testing']) && $request->query('api_key')) {
            return 'query_parameter';
        }

        return 'unknown';
    }
}
