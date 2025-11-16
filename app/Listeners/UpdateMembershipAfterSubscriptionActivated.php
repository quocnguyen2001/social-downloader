<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\SubscriptionActivatedEvent;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class UpdateMembershipAfterSubscriptionActivated
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {}

    public function handle(SubscriptionActivatedEvent $event): void
    {
        Log::info('Processing subscription activation for membership assignment', [
            'subscription_id' => $event->getSubscriptionId(),
            'user_id' => $event->getUserId(),
            'membership_plan_id' => $event->getMembershipPlanId(),
        ]);

        try {
            if (! $event->hasMembershipPlan()) {
                Log::info('Subscription has no membership plan, skipping membership assignment', [
                    'subscription_id' => $event->getSubscriptionId(),
                ]);

                return;
            }

            $user = $event->getUser();
            $membershipPlan = $event->getMembershipPlan();

            if (! $membershipPlan) {
                Log::warning('Membership plan not found for subscription', [
                    'subscription_id' => $event->getSubscriptionId(),
                    'membership_plan_id' => $event->getMembershipPlanId(),
                ]);

                return;
            }

            $this->subscriptionService->assignMembershipToUser($user, $membershipPlan);

            Log::info('Membership successfully assigned to user', [
                'subscription_id' => $event->getSubscriptionId(),
                'user_id' => $event->getUserId(),
                'membership_plan_id' => $event->getMembershipPlanId(),
                'membership_plan_name' => $membershipPlan->name ?? 'Unknown',
            ]);

        } catch (Throwable $exception) {
            Log::error('Failed to assign membership to user after subscription activation', [
                'subscription_id' => $event->getSubscriptionId(),
                'user_id' => $event->getUserId(),
                'membership_plan_id' => $event->getMembershipPlanId(),
                'exception' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
