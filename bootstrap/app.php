<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ValidateApiRequest::class,
            \App\Http\Middleware\SanitizeInput::class,
        ]);

        // Add cleanup middleware to all requests
        $middleware->append(\App\Http\Middleware\ClearAuthenticatedApiKey::class);

        $middleware->alias([
            'api.auth' => \App\Http\Middleware\ApiKeyAuthentication::class,
            'guest.rate.limit' => \App\Http\Middleware\GuestApiRateLimit::class,
            'auth.rate.limit' => \App\Http\Middleware\AuthenticatedApiRateLimit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
