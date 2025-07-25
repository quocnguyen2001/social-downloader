<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiUsageTracker;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guest API Rate Limiting Middleware
 *
 * Enforces rate limits and validates permissions for unauthenticated users
 * based on IP address and guest API limit settings.
 */
class GuestApiRateLimit
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
            $ipAddress = $this->getClientIpAddress($request);

            // Get guest API limits
            $guestLimits = guestApiLimits();

            $limits = [
                'hourly_request_limit' => $guestLimits->hourly_request_limit,
                'daily_request_limit' => $guestLimits->daily_request_limit,
                'allowed_qualities' => $guestLimits->allowed_qualities,
                'allowed_formats' => $guestLimits->allowed_formats,
                'allowed_platforms' => $guestLimits->allowed_platforms,
            ];

            // Check current usage against limits
            $usageCheck = $this->usageTracker->checkGuestLimits($ipAddress, $limits);

            if (! $usageCheck['allowed']) {
                return $this->rateLimitExceededResponse($usageCheck, $ipAddress);
            }

            // Validate quality and format permissions if provided in request
            $validationResult = $this->validateRequestPermissions($request, $limits);
            if ($validationResult !== true) {
                return $validationResult;
            }

            // Increment usage counter
            $this->usageTracker->incrementGuestUsage($ipAddress);

            // Add rate limit headers to response
            $response = $next($request);
            $this->addRateLimitHeaders($response, $usageCheck);

            // Log successful request
            Log::info('Guest API request allowed', [
                'ip_address' => $ipAddress,
                'endpoint' => $request->path(),
                'current_usage' => $usageCheck['current_usage'],
                'limits' => $usageCheck['limits'],
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('Guest rate limit middleware error', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'endpoint' => $request->path(),
            ]);

            return $this->errorResponse('Rate limiting service temporarily unavailable');
        }
    }

    /**
     * Get the client's IP address with proxy support.
     */
    private function getClientIpAddress(Request $request): string
    {
        // Check for IP from various headers (for proxy/load balancer support)
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Standard proxy header
            'HTTP_X_REAL_IP',            // Nginx proxy
            'HTTP_X_FORWARDED',          // Alternative
            'HTTP_FORWARDED_FOR',        // Alternative
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR',               // Standard
        ];

        foreach ($ipHeaders as $header) {
            $ip = $request->server($header);
            if (! empty($ip) && $ip !== 'unknown') {
                // Handle comma-separated IPs (take the first one)
                $ip = trim(explode(',', $ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to Laravel's ip() method
        return $request->ip();
    }

    /**
     * Validate request permissions against guest limits.
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
                'Quality not allowed for guest users',
                'QUALITY_NOT_ALLOWED',
                [
                    'requested_quality' => $quality,
                    'allowed_qualities' => $limits['allowed_qualities'],
                ]
            );
        }

        // Validate format if provided
        if ($format && ! in_array($format, $limits['allowed_formats'])) {
            return $this->permissionDeniedResponse(
                'Format not allowed for guest users',
                'FORMAT_NOT_ALLOWED',
                [
                    'requested_format' => $format,
                    'allowed_formats' => $limits['allowed_formats'],
                ]
            );
        }

        return true;
    }

    /**
     * Return rate limit exceeded response.
     */
    private function rateLimitExceededResponse(array $usageCheck, string $ipAddress): JsonResponse
    {
        $message = $this->buildRateLimitMessage($usageCheck);

        Log::warning('Guest rate limit exceeded', [
            'ip_address' => $ipAddress,
            'current_usage' => $usageCheck['current_usage'],
            'limits' => $usageCheck['limits'],
            'reset_times' => $usageCheck['reset_times'],
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => 'RATE_LIMIT_EXCEEDED',
            'limits' => [
                'hourly' => [
                    'limit' => $usageCheck['limits']['hourly'],
                    'used' => $usageCheck['current_usage']['hourly'],
                    'remaining' => max(0, $usageCheck['limits']['hourly'] - $usageCheck['current_usage']['hourly']),
                    'reset_time' => $usageCheck['reset_times']['hourly']->toISOString(),
                ],
                'daily' => [
                    'limit' => $usageCheck['limits']['daily'],
                    'used' => $usageCheck['current_usage']['daily'],
                    'remaining' => max(0, $usageCheck['limits']['daily'] - $usageCheck['current_usage']['daily']),
                    'reset_time' => $usageCheck['reset_times']['daily']->toISOString(),
                ],
            ],
        ], 429);
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
    private function buildRateLimitMessage(array $usageCheck): string
    {
        $hourlyExceeded = $usageCheck['limits']['hourly'] > 0 &&
                         $usageCheck['current_usage']['hourly'] >= $usageCheck['limits']['hourly'];
        $dailyExceeded = $usageCheck['limits']['daily'] > 0 &&
                        $usageCheck['current_usage']['daily'] >= $usageCheck['limits']['daily'];

        if ($hourlyExceeded && $dailyExceeded) {
            return 'Both hourly and daily rate limits exceeded. Please try again later.';
        } elseif ($hourlyExceeded) {
            $resetTime = $usageCheck['reset_times']['hourly']->diffForHumans();

            return "Hourly rate limit exceeded. Limit resets {$resetTime}.";
        } elseif ($dailyExceeded) {
            $resetTime = $usageCheck['reset_times']['daily']->diffForHumans();

            return "Daily rate limit exceeded. Limit resets {$resetTime}.";
        }

        return 'Rate limit exceeded. Please try again later.';
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(Response $response, array $usageCheck): void
    {
        $response->headers->set('X-RateLimit-Limit-Hourly', (string) $usageCheck['limits']['hourly']);
        $response->headers->set('X-RateLimit-Remaining-Hourly', (string) max(0, $usageCheck['limits']['hourly'] - $usageCheck['current_usage']['hourly']));
        $response->headers->set('X-RateLimit-Reset-Hourly', (string) $usageCheck['reset_times']['hourly']->timestamp);

        $response->headers->set('X-RateLimit-Limit-Daily', (string) $usageCheck['limits']['daily']);
        $response->headers->set('X-RateLimit-Remaining-Daily', (string) max(0, $usageCheck['limits']['daily'] - $usageCheck['current_usage']['daily']));
        $response->headers->set('X-RateLimit-Reset-Daily', (string) $usageCheck['reset_times']['daily']->timestamp);
    }
}
