<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\SubscriptionActivatedEvent;
use App\Listeners\UpdateMembershipAfterSubscriptionActivated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class SubscriptionServiceProvider extends ServiceProvider
{
    protected $listen = [
        SubscriptionActivatedEvent::class => [
            UpdateMembershipAfterSubscriptionActivated::class,
        ],
    ];

    public function register(): void {}

    public function boot(): void {}
}
