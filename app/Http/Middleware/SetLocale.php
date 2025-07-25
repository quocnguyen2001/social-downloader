<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get locale from session, request parameter, or default to 'en'
        $locale = $request->get('locale')
                 ?? Session::get('locale')
                 ?? config('app.locale', 'en');

        // Validate locale (only allow 'en' and 'vi')
        if (! in_array($locale, ['en', 'vi'])) {
            $locale = 'en';
        }

        // Set the application locale
        App::setLocale($locale);

        // Store locale in session for future requests
        Session::put('locale', $locale);

        return $next($request);
    }
}
