<?php

use App\Http\Middleware\ApiKeyAuthentication;
use App\Http\Middleware\AuthenticatedApiRateLimit;
use App\Http\Middleware\ClearAuthenticatedApiKey;
use App\Http\Middleware\GuestApiRateLimit;
use App\Http\Middleware\SanitizeInput;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ValidateApiRequest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->api(prepend: [
            SecurityHeaders::class,
            ValidateApiRequest::class,
            SanitizeInput::class,
        ]);

        // Add cleanup middleware to all requests
        $middleware->append(ClearAuthenticatedApiKey::class);

        $middleware->alias([
            'api.auth' => ApiKeyAuthentication::class,
            'guest.rate.limit' => GuestApiRateLimit::class,
            'auth.rate.limit' => AuthenticatedApiRateLimit::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Schedule the file deletion processor to run every hour
        $schedule->command('scheduled-deletions:process')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/scheduled-deletions.log'));

        // Schedule the expired downloads cleanup to run every hour
        $schedule->command('downloads:cleanup-expired')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/cleanup-expired-downloads.log'));

        // Schedule the bank transfer checker to run every 5 seconds
        $schedule->command('bank-transfer:check --force')
            ->everyFiveSeconds()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/bank-transfer-check.log'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
