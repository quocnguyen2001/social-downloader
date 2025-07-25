<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sanitize input data
        $this->sanitizeRequestData($request);

        return $next($request);
    }

    /**
     * Sanitize request data to prevent XSS and other attacks.
     */
    private function sanitizeRequestData(Request $request): void
    {
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);
        $request->replace($sanitized);
    }

    /**
     * Recursively sanitize an array of data.
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitizedKey = $this->sanitizeString((string) $key);

            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeString($value);
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a string value.
     */
    private function sanitizeString(string $value): string
    {
        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Trim whitespace
        $value = trim($value);

        // Remove control characters except newlines and tabs
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        // For specific fields that should not contain HTML, strip tags
        // This is a basic implementation - you might want to be more selective
        if ($this->shouldStripHtml($value)) {
            $value = strip_tags($value);
        }

        return $value;
    }

    /**
     * Determine if HTML should be stripped from a value.
     */
    private function shouldStripHtml(string $value): bool
    {
        // Strip HTML from values that contain suspicious patterns
        $suspiciousPatterns = [
            '/<script/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i', // Event handlers like onclick, onload, etc.
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
