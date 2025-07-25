<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ApiUsageTracker;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticated API Rate Limiting Middleware
 *
 * Enforces rate limits and validates permissions for authenticated users
 * based on their membership plan or default authenticated user settings.
 */
class AuthenticatedApiRateLimit
{
    public function __construct(
        private ApiUsageTracker $usageTracker
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            /** @var User $user */
            $user = $request->user();

            if (! $user) {
                return $this->unauthorizedResponse();
            }

            // Get user-specific API limits (membership plan or default)
            $limits = getUserApiLimits($user);

            // Check current usage against limits
            $usageCheck = $this->usageTracker->checkUserLimits($user, $limits);

            if (! $usageCheck['allowed']) {
                return $this->rateLimitExceededResponse($usageCheck, $user, $limits);
            }

            // Validate quality and format permissions if provided in request
            $validationResult = $this->validateRequestPermissions($request, $limits);
            if ($validationResult !== true) {
                return $validationResult;
            }

            // Check membership plan expiration
            if ($user->membershipPlan && ! $user->hasActiveMembership()) {
                return $this->membershipExpiredResponse($user);
            }

            // Increment usage counter
            $this->usageTracker->incrementUserUsage($user);

            // Add rate limit headers to response
            $response = $next($request);
            $this->addRateLimitHeaders($response, $usageCheck, $limits);

            // Log successful request
            Log::info('Authenticated API request allowed', [
                'user_id' => $user->id,
                'endpoint' => $request->path(),
                'current_usage' => $usageCheck['current_usage'],
                'limits' => $usageCheck['limits'],
                'membership_plan' => $limits['plan_name'],
                'limits_source' => $limits['source'],
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Authenticated rate limit middleware error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'endpoint' => $request->path(),
            ]);

            return $this->errorResponse('Rate limiting service temporarily unavailable');
        }
    }

    /**
     * Validate request permissions against user limits.
     *
     * @return JsonResponse|true
     */
    private function validateRequestPermissions(Request $request, array $limits): JsonResponse|bool
    {
        $quality = $request->input('quality');
        $format = $request->input('format');

        // Validate quality if provided
        if ($quality && ! in_array($quality, $limits['allowed_qualities'])) {
            return $this->permissionDeniedResponse(
                'Quality not allowed for your membership plan',
                'QUALITY_NOT_ALLOWED',
                [
                    'requested_quality' => $quality,
                    'allowed_qualities' => $limits['allowed_qualities'],
                    'membership_plan' => $limits['plan_name'],
                ]
            );
        }

        // Validate format if provided
        if ($format && ! in_array($format, $limits['allowed_formats'])) {
            return $this->permissionDeniedResponse(
                'Format not allowed for your membership plan',
                'FORMAT_NOT_ALLOWED',
                [
                    'requested_format' => $format,
                    'allowed_formats' => $limits['allowed_formats'],
                    'membership_plan' => $limits['plan_name'],
                ]
            );
        }

        return true;
    }

    /**
     * Return rate limit exceeded response.
     */
    private function rateLimitExceededResponse(array $usageCheck, User $user, array $limits): JsonResponse
    {
        $message = $this->buildRateLimitMessage($usageCheck, $limits);

        Log::warning('User rate limit exceeded', [
            'user_id' => $user->id,
            'current_usage' => $usageCheck['current_usage'],
            'limits' => $usageCheck['limits'],
            'reset_times' => $usageCheck['reset_times'],
            'membership_plan' => $limits['plan_name'],
            'limits_source' => $limits['source'],
        ]);

        $responseData = [
            'success' => false,
            'message' => $message,
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'limits' => [],
            'membership_info' => [
                'plan_name' => $limits['plan_name'],
                'source' => $limits['source'],
            ],
        ];

        // Add hourly limits if they exist
        if (isset($usageCheck['limits']['hourly']) && $usageCheck['limits']['hourly'] > 0) {
            $responseData['limits']['hourly'] = [
                'limit' => $usageCheck['limits']['hourly'],
                'used' => $usageCheck['current_usage']['hourly'],
                'remaining' => max(0, $usageCheck['limits']['hourly'] - $usageCheck['current_usage']['hourly']),
                'reset_time' => $usageCheck['reset_times']['hourly']->toISOString(),
            ];
        }

        // Add daily limits
        if (isset($usageCheck['limits']['daily']) && $usageCheck['limits']['daily'] > 0) {
            $responseData['limits']['daily'] = [
                'limit' => $usageCheck['limits']['daily'],
                'used' => $usageCheck['current_usage']['daily'],
                'remaining' => max(0, $usageCheck['limits']['daily'] - $usageCheck['current_usage']['daily']),
                'reset_time' => $usageCheck['reset_times']['daily']->toISOString(),
            ];
        }

        // Add total limits if they exist (for membership plans)
        if (isset($usageCheck['limits']['total']) && $usageCheck['limits']['total'] > 0) {
            $responseData['limits']['total'] = [
                'limit' => $usageCheck['limits']['total'],
                'used' => $usageCheck['current_usage']['total'],
                'remaining' => max(0, $usageCheck['limits']['total'] - $usageCheck['current_usage']['total']),
                'reset_time' => null, // Total limits don't reset
            ];
        }

        return response()->json($responseData, 429);
    }

    /**
     * Return membership expired response.
     */
    private function membershipExpiredResponse(User $user): JsonResponse
    {
        Log::warning('Membership plan expired', [
            'user_id' => $user->id,
            'membership_plan_id' => $user->membership_plan_id,
            'membership_expires_at' => $user->membership_expires_at,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Your membership plan has expired. Please renew to continue using premium features.',
            'error_code' => 'MEMBERSHIP_EXPIRED',
            'membership_info' => [
                'plan_name' => $user->membershipPlan?->name,
                'expired_at' => $user->membership_expires_at?->toISOString(),
            ],
        ], 403);
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Authentication required',
            'error_code' => 'UNAUTHORIZED',
        ], 401);
    }

    /**
     * Return permission denied response.
     */
    private function permissionDeniedResponse(string $message, string $errorCode, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'details' => $details,
        ], 403);
    }

    /**
     * Return generic error response.
     */
    private function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'INTERNAL_ERROR',
        ], 500);
    }

    /**
     * Build rate limit message based on which limit was exceeded.
     */
    private function buildRateLimitMessage(array $usageCheck, array $limits): string
    {
        $hourlyExceeded = isset($usageCheck['limits']['hourly']) &&
                         $usageCheck['limits']['hourly'] > 0 &&
                         $usageCheck['current_usage']['hourly'] >= $usageCheck['limits']['hourly'];

        $dailyExceeded = isset($usageCheck['limits']['daily']) &&
                        $usageCheck['limits']['daily'] > 0 &&
                        $usageCheck['current_usage']['daily'] >= $usageCheck['limits']['daily'];

        $totalExceeded = isset($usageCheck['limits']['total']) &&
                        $usageCheck['limits']['total'] > 0 &&
                        $usageCheck['current_usage']['total'] >= $usageCheck['limits']['total'];

        $planInfo = $limits['plan_name'] ? " for your {$limits['plan_name']} plan" : '';

        if ($totalExceeded) {
            return "Total download limit exceeded{$planInfo}. Please upgrade your plan or contact support.";
        } elseif ($hourlyExceeded && $dailyExceeded) {
            return "Both hourly and daily rate limits exceeded{$planInfo}. Please try again later.";
        } elseif ($hourlyExceeded) {
            $resetTime = $usageCheck['reset_times']['hourly']->diffForHumans();

            return "Hourly rate limit exceeded{$planInfo}. Limit resets {$resetTime}.";
        } elseif ($dailyExceeded) {
            $resetTime = $usageCheck['reset_times']['daily']->diffForHumans();

            return "Daily rate limit exceeded{$planInfo}. Limit resets {$resetTime}.";
        }

        return "Rate limit exceeded{$planInfo}. Please try again later.";
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(Response $response, array $usageCheck, array $limits): void
    {
        // Add hourly headers if applicable
        if (isset($usageCheck['limits']['hourly'])) {
            $response->headers->set('X-RateLimit-Limit-Hourly', (string) $usageCheck['limits']['hourly']);
            $response->headers->set('X-RateLimit-Remaining-Hourly', (string) max(0, $usageCheck['limits']['hourly'] - $usageCheck['current_usage']['hourly']));
            $response->headers->set('X-RateLimit-Reset-Hourly', (string) $usageCheck['reset_times']['hourly']->timestamp);
        }

        // Add daily headers
        if (isset($usageCheck['limits']['daily'])) {
            $response->headers->set('X-RateLimit-Limit-Daily', (string) $usageCheck['limits']['daily']);
            $response->headers->set('X-RateLimit-Remaining-Daily', (string) max(0, $usageCheck['limits']['daily'] - $usageCheck['current_usage']['daily']));
            $response->headers->set('X-RateLimit-Reset-Daily', (string) $usageCheck['reset_times']['daily']->timestamp);
        }

        // Add total headers if applicable (for membership plans)
        if (isset($usageCheck['limits']['total']) && $usageCheck['limits']['total'] > 0) {
            $response->headers->set('X-RateLimit-Limit-Total', (string) $usageCheck['limits']['total']);
            $response->headers->set('X-RateLimit-Remaining-Total', (string) max(0, $usageCheck['limits']['total'] - $usageCheck['current_usage']['total']));
        }

        // Add membership plan info
        $response->headers->set('X-Membership-Plan', $limits['plan_name'] ?? 'default');
        $response->headers->set('X-Limits-Source', $limits['source']);
    }
}
