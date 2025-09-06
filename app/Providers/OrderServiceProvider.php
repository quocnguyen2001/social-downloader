<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\OrderCompletedEvent;
use App\Listeners\UpdateMemberShipToUserWhenOrderCompleted;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class OrderServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderCompletedEvent::class => [
            UpdateMemberShipToUserWhenOrderCompleted::class,
        ],
    ];

    public function register(): void {}

    public function boot(): void
    {

    }
}
