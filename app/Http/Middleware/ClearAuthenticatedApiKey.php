<?php

namespace App\Http\Middleware;

use App\Services\AuthenticatedApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to clear the authenticated API key singleton after request completion.
 *
 * This middleware ensures that the AuthenticatedApiKey singleton is properly
 * cleaned up at the end of each request to prevent memory leaks and data
 * bleeding between requests.
 */
class ClearAuthenticatedApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Process the request
        $response = $next($request);

        // Clear the authenticated API key after request completion
        $this->clearAuthenticatedApiKey();

        return $response;
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Ensure cleanup happens even if not cleared in handle method
        $this->clearAuthenticatedApiKey();
    }

    /**
     * Clear the authenticated API key singleton.
     */
    private function clearAuthenticatedApiKey(): void
    {
        if (AuthenticatedApiKey::has()) {
            $debugInfo = AuthenticatedApiKey::getDebugInfo();

            Log::debug('Clearing authenticated API key singleton', [
                'api_key_id' => $debugInfo['api_key_id'],
                'request_id' => $debugInfo['request_id'],
                'total_duration_ms' => $debugInfo['set_duration_ms'],
            ]);

            AuthenticatedApiKey::clear();
        }
    }
}
