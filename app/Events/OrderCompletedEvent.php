<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public array $metadata = []
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->id;
    }

    public function getUserId(): int
    {
        return $this->order->user_id;
    }

    public function getMembershipPlanId(): ?int
    {
        return $this->order->membership_plan_id;
    }

    public function getOrderTotal(): float
    {
        return (float) $this->order->total;
    }

    public function getOrderStatus(): string
    {
        return $this->order->status->value;
    }

    public function getCompletedAt(): ?Carbon
    {
        return $this->order->completed_at;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function hasMembershipPlan(): bool
    {
        return $this->order->membership_plan_id !== null;
    }

    public function getUser(): \App\Models\User
    {
        return $this->order->user;
    }

    public function getMembershipPlan(): ?\App\Models\MembershipPlan
    {
        return $this->order->membershipPlan;
    }

    public function getTransaction(): ?\App\Models\Transaction
    {
        return $this->order->transaction;
    }

    public function toArray(): array
    {
        return [
            'order_id' => $this->order->id,
            'user_id' => $this->order->user_id,
            'membership_plan_id' => $this->order->membership_plan_id,
            'total' => $this->order->total,
            'status' => $this->order->status->value,
            'completed_at' => $this->order->completed_at?->toISOString(),
            'payment_id' => $this->order->payment_id,
            'metadata' => $this->metadata,
        ];
    }
}
