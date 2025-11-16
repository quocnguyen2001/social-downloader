<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public array $metadata = []
    ) {}

    public function getSubscription(): Subscription
    {
        return $this->subscription;
    }

    public function getSubscriptionId(): string
    {
        return $this->subscription->id;
    }

    public function getUserId(): int
    {
        return $this->subscription->user_id;
    }

    public function getMembershipPlanId(): ?int
    {
        return $this->subscription->membership_plan_id;
    }

    public function getSubscriptionTotal(): float
    {
        return (float) $this->subscription->total;
    }

    public function getSubscriptionStatus(): string
    {
        return $this->subscription->status->value;
    }

    public function getCompletedAt(): ?Carbon
    {
        return $this->subscription->completed_at;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function hasMembershipPlan(): bool
    {
        return $this->subscription->membership_plan_id !== null;
    }

    public function getUser(): \App\Models\User
    {
        return $this->subscription->user;
    }

    public function getMembershipPlan(): ?\App\Models\MembershipPlan
    {
        return $this->subscription->membershipPlan;
    }

    public function getTransaction(): ?\App\Models\Transaction
    {
        return $this->subscription->transaction;
    }

    public function toArray(): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->subscription->user_id,
            'membership_plan_id' => $this->subscription->membership_plan_id,
            'total' => $this->subscription->total,
            'status' => $this->subscription->status->value,
            'completed_at' => $this->subscription->completed_at?->toISOString(),
            'payment_id' => $this->subscription->payment_id,
            'metadata' => $this->metadata,
        ];
    }
}
