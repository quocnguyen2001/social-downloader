<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderCompletedEvent;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class UpdateMemberShipToUserWhenOrderCompleted
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function handle(OrderCompletedEvent $event): void
    {
        Log::info('Processing order completion for membership assignment', [
            'order_id' => $event->getOrderId(),
            'user_id' => $event->getUserId(),
            'membership_plan_id' => $event->getMembershipPlanId(),
        ]);

        try {
            if (! $event->hasMembershipPlan()) {
                Log::info('Order has no membership plan, skipping membership assignment', [
                    'order_id' => $event->getOrderId(),
                ]);

                return;
            }

            $user = $event->getUser();
            $membershipPlan = $event->getMembershipPlan();

            if (! $membershipPlan) {
                Log::warning('Membership plan not found for order', [
                    'order_id' => $event->getOrderId(),
                    'membership_plan_id' => $event->getMembershipPlanId(),
                ]);

                return;
            }

            $this->orderService->assignMembershipToUser($user, $membershipPlan);

            Log::info('Membership successfully assigned to user', [
                'order_id' => $event->getOrderId(),
                'user_id' => $event->getUserId(),
                'membership_plan_id' => $event->getMembershipPlanId(),
                'membership_plan_name' => $membershipPlan->name ?? 'Unknown',
            ]);

        } catch (Throwable $exception) {
            Log::error('Failed to assign membership to user after order completion', [
                'order_id' => $event->getOrderId(),
                'user_id' => $event->getUserId(),
                'membership_plan_id' => $event->getMembershipPlanId(),
                'exception' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
