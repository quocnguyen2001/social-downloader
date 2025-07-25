<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only apply security headers if enabled
        if (! config('api_security.security_headers.enabled', true)) {
            return $response;
        }

        // Apply security headers
        $this->applySecurityHeaders($response);

        return $response;
    }

    /**
     * Apply security headers to the response.
     */
    private function applySecurityHeaders(Response $response): void
    {
        $headers = config('api_security.security_headers.headers', []);

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Additional dynamic headers
        $this->applyDynamicHeaders($response);
    }

    /**
     * Apply dynamic security headers based on request context.
     */
    private function applyDynamicHeaders(Response $response): void
    {
        // Set Strict-Transport-Security for HTTPS
        if (request()->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Set Content-Type specific headers
        $contentType = $response->headers->get('Content-Type', '');

        if (str_contains($contentType, 'application/json')) {
            // For JSON responses, prevent MIME sniffing
            $response->headers->set('X-Content-Type-Options', 'nosniff');

            // Prevent caching of sensitive API responses
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // Set CORS headers for API endpoints
        if (request()->is('api/*')) {
            $this->applyCorsHeaders($response);
        }

        // Set additional API-specific headers
        $this->applyApiHeaders($response);
    }

    /**
     * Apply CORS headers for API endpoints.
     */
    private function applyCorsHeaders(Response $response): void
    {
        $allowedOrigins = config('cors.allowed_origins', ['*']);
        $origin = request()->header('Origin');

        // Set appropriate Access-Control-Allow-Origin
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?: '*');
        }

        $response->headers->set(
            'Access-Control-Allow-Methods',
            implode(', ', config('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']))
        );

        $response->headers->set(
            'Access-Control-Allow-Headers',
            implode(', ', config('cors.allowed_headers', [
                'Content-Type',
                'Authorization',
                'X-Requested-With',
                'Accept',
                'Origin',
            ]))
        );

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours
    }

    /**
     * Apply API-specific security headers.
     */
    private function applyApiHeaders(Response $response): void
    {
        // API versioning header
        $response->headers->set('X-API-Version', config('app.api_version', '1.0'));

        // Rate limiting information
        if (request()->is('api/*')) {
            $this->applyRateLimitHeaders($response);
        }

        // Security policy for API
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'none'; frame-ancestors 'none';"
        );

        // Prevent embedding in frames
        $response->headers->set('X-Frame-Options', 'DENY');

        // Feature policy
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=(), speaker=()'
        );
    }

    /**
     * Apply rate limiting headers if available.
     */
    private function applyRateLimitHeaders(Response $response): void
    {
        // These would typically be set by rate limiting middleware
        // This is just a placeholder for future implementation
        $response->headers->set('X-RateLimit-Limit', '1000');
        $response->headers->set('X-RateLimit-Remaining', '999');
        $response->headers->set('X-RateLimit-Reset', (string) (time() + 3600));
    }
}
