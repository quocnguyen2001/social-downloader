<?php

namespace App\Http\Middleware;

use App\Enums\ApiKeyStatus;
use App\Models\ApiKey;
use App\Services\ApiKeyCacheService;
use App\Services\AuthenticatedApiKey;
use Closure;
use Exception;
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
            $apiKeyValue = $this->extractApiKey($request);

            if (! $apiKeyValue) {
                return $this->unauthorizedResponse('API key is required');
            }

            $apiKey = $this->validateApiKey($apiKeyValue);

            if (! $apiKey) {
                return $this->unauthorizedResponse('Invalid API key');
            }

            if ($apiKey->status !== ApiKeyStatus::ACTIVE) {
                return $this->unauthorizedResponse('API key is not active');
            }

            AuthenticatedApiKey::set($apiKey, [
                'authentication_method' => $this->getAuthenticationMethod($request),
                'endpoint' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $rateLimitResult = $this->checkRateLimit($apiKey, $request);
            if ($rateLimitResult !== true) {
                return $rateLimitResult;
            }

            $usageLimitResult = $this->checkUsageLimits($apiKey);
            if ($usageLimitResult !== true) {
                return $usageLimitResult;
            }

            if (! empty($scopes) && ! $this->hasRequiredScopes($apiKey, $scopes)) {
                return $this->forbiddenResponse('Insufficient permissions for this endpoint');
            }

            $request->attributes->set('api_key', $apiKey);

            Log::info('API key authenticated successfully', [
                'api_key_id' => $apiKey->id,
                'tier' => $apiKey->tier,
                'endpoint' => $request->path(),
                'ip' => $request->ip(),
                'request_id' => AuthenticatedApiKey::getRequestId(),
            ]);

            return $next($request);

        } catch (Exception $e) {
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
        $apiKeyHeader = $request->header('X-API-Key');

        if ($apiKeyHeader) {
            return $apiKeyHeader;
        }

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

        $apiKey = $this->cacheService->getCachedApiKey($keyHash);

        if ($apiKey === null) {
            $apiKey = ApiKey::where('key_hash', $keyHash)
                ->where('status', ApiKeyStatus::ACTIVE)
                ->first();

            if ($apiKey) {
                $this->cacheService->cacheApiKey($keyHash, $apiKey);
            } else {
                $this->cacheService->cacheInvalidApiKey($keyHash);
            }
        } elseif ($apiKey === false) {
            return null;
        }

        return $apiKey;
    }

    private function checkRateLimit(ApiKey $apiKey, Request $request): Response|bool
    {
        $user = $request->user();
        $rateLimitKey = "api_rate_limit:{$apiKey->id}";
        $maxAttempts = $this->getRateLimitForTier('none');
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            Log::warning('API rate limit exceeded', [
                'api_key_id' => $apiKey->id,
                'tier' => null,
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

    private function hasRequiredScopes(ApiKey $apiKey, array $requiredScopes): bool
    {
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

    private function getRateLimitForTier(string $tier): int
    {
        return match ($tier) {
            'premium' => 1000, // 1000 requests per minute
            'pro' => 300,      // 300 requests per minute
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
