<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Validate request headers
        if (! $this->validateHeaders($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request headers',
                'errors' => ['headers' => ['Required headers are missing or invalid']],
            ], 400);
        }

        // Validate request size
        if (! $this->validateRequestSize($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Request payload too large',
                'errors' => ['payload' => ['Request size exceeds maximum allowed limit']],
            ], 413);
        }

        // Validate content type for POST/PUT/PATCH requests
        if (! $this->validateContentType($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid content type',
                'errors' => ['content_type' => ['Content-Type must be application/json for API requests']],
            ], 415);
        }

        // Log suspicious requests
        $this->logSuspiciousActivity($request);

        return $next($request);
    }

    /**
     * Validate required headers.
     */
    private function validateHeaders(Request $request): bool
    {
        // Check for Accept header
        if (! $request->hasHeader('Accept')) {
            return false;
        }

        // Validate Accept header contains application/json
        $acceptHeader = $request->header('Accept');
        if (! str_contains($acceptHeader, 'application/json') && ! str_contains($acceptHeader, '*/*')) {
            return false;
        }

        return true;
    }

    /**
     * Validate request size.
     */
    private function validateRequestSize(Request $request): bool
    {
        $maxSize = config('api_security.max_request_size', 1024 * 1024); // 1MB default
        $contentLength = $request->header('Content-Length', 0);

        return (int) $contentLength <= $maxSize;
    }

    /**
     * Validate content type for requests with body.
     */
    private function validateContentType(Request $request): bool
    {
        $methodsWithBody = ['POST', 'PUT', 'PATCH'];

        if (! in_array($request->method(), $methodsWithBody)) {
            return true;
        }

        $contentType = $request->header('Content-Type');

        // Allow empty content type for requests with no body
        if (! $contentType && ! $request->getContent()) {
            return true;
        }

        // Check for application/json or multipart/form-data (for file uploads)
        return str_contains($contentType, 'application/json') ||
               str_contains($contentType, 'multipart/form-data');
    }

    /**
     * Log suspicious activity.
     */
    private function logSuspiciousActivity(Request $request): void
    {
        $suspiciousPatterns = [
            'sql injection' => '/(\bunion\b|\bselect\b|\binsert\b|\bdelete\b|\bdrop\b|\bupdate\b).*(\bfrom\b|\binto\b|\bwhere\b)/i',
            'xss attempt' => '/<script|javascript:|vbscript:|onload=|onerror=/i',
            'path traversal' => '/\.\.[\/\\\\]/i',
            'command injection' => '/(\||;|&|\$\(|\`)/i',
        ];

        $requestData = json_encode([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'input' => $request->all(),
        ]);

        foreach ($suspiciousPatterns as $type => $pattern) {
            if (preg_match($pattern, $requestData)) {
                Log::warning("Suspicious {$type} detected", [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'input' => $request->all(),
                    'type' => $type,
                ]);
                break;
            }
        }
    }
}
