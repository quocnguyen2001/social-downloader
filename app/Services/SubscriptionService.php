<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class SubscriptionService
{
    public function __construct(
        private PaymentService $paymentService
    ) {}

    public function createSubscription(User $user, array $data): Subscription
    {
        return DB::transaction(function () use ($user, $data) {
            $membershipPlan = $this->validateMembershipPlan($data['membership_plan_id']);

            $subtotal = $membershipPlan->price;
            $discount = $this->calculateDiscount((float) $subtotal, $data['coupon_code'] ?? null);
            $total = $subtotal - $discount;

            $subscription = Subscription::query()->create([
                'user_id' => $user->id,
                'membership_plan_id' => $membershipPlan->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => SubscriptionStatus::PENDING,
            ]);

            $paymentMethod = PaymentMethod::from($data['payment_method']);
            $transaction = $this->paymentService->createTransaction(
                $user,
                $subscription,
                $paymentMethod,
                $total
            );

            $subscription->update(['payment_id' => $transaction->id]);

            Log::info('Subscription created successfully', [
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'membership_plan_id' => $membershipPlan->id,
                'total' => $total,
                'payment_method' => $paymentMethod->value,
            ]);

            return $subscription->load(['membershipPlan', 'transaction', 'user']);
        });
    }

    public function getUserSubscriptions(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->subscriptions()
            ->with(['membershipPlan', 'transaction'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function updateSubscriptionStatus(Subscription $subscription, SubscriptionStatus $status): Subscription
    {
        $subscription->update(['status' => $status]);

        Log::info('Subscription status updated', [
            'subscription_id' => $subscription->id,
            'old_status' => $subscription->getOriginal('status'),
            'new_status' => $status->value,
        ]);

        return $subscription->fresh();
    }

    public function completeSubscription(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $subscription = $this->updateSubscriptionStatus($subscription, SubscriptionStatus::COMPLETED);

            if ($subscription->membershipPlan) {
                $this->assignMembershipToUser($subscription->user, $subscription->membershipPlan);
            }

            Log::info('Subscription completed successfully', [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'membership_plan_id' => $subscription->membership_plan_id,
            ]);

            return $subscription;
        });
    }

    private function validateMembershipPlan(int|string $membershipPlanId): MembershipPlan
    {
        $plan = MembershipPlan::query()->where('id', $membershipPlanId)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            throw new ModelNotFoundException(__('messages.error.membership_plan_not_found'));
        }

        return $plan;
    }

    private function calculateDiscount(float $subtotal, ?string $couponCode): float
    {
        if (! $couponCode) {
            return 0.0;
        }

        Log::info('Coupon code provided but not processed', [
            'coupon_code' => $couponCode,
            'subtotal' => $subtotal,
        ]);

        return 0.0;
    }

    public function assignMembershipToUser(User $user, MembershipPlan $plan): void
    {
        $now = Carbon::now();

        $expiresAt = match ($plan->billing_cycle) {
            'yearly' => $now->addYear(),
            'lifetime' => null,
            default => $now->addMonth(),
        };

        $user->assignMembershipPlan($plan, $expiresAt);

        Log::info('Membership plan assigned to user', [
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'expires_at' => $expiresAt?->toISOString(),
        ]);
    }
}
