<?php

namespace App\Providers;

use App\Models\ApiRequest;
use App\Observers\ApiRequestObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        ApiRequest::observe(ApiRequestObserver::class);
    }
}
