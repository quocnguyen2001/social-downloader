<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling order operations.
 */
class OrderService
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Create a new order for a user.
     */
    public function createOrder(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data) {
            // Validate membership plan
            $membershipPlan = $this->validateMembershipPlan($data['membership_plan_id']);

            // Calculate order totals
            $subtotal = $membershipPlan->price;
            $discount = $this->calculateDiscount((float) $subtotal, $data['coupon_code'] ?? null);
            $total = $subtotal - $discount;

            // Create the order
            $order = Order::query()->create([
                'user_id' => $user->id,
                'membership_plan_id' => $membershipPlan->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'status' => OrderStatus::PENDING,
            ]);

            // Create payment transaction
            $paymentMethod = PaymentMethod::from($data['payment_method']);
            $transaction = $this->paymentService->createTransaction(
                $user,
                $order,
                $paymentMethod,
                $total
            );

            // Update order with payment reference
            $order->update(['payment_id' => $transaction->id]);

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'membership_plan_id' => $membershipPlan->id,
                'total' => $total,
                'payment_method' => $paymentMethod->value,
            ]);

            return $order->load(['membershipPlan', 'transaction', 'user']);
        });
    }

    /**
     * Get orders for a specific user.
     */
    public function getUserOrders(User $user, array $filters = [])
    {
        $query = $user->orders()
            ->with(['membershipPlan', 'transaction'])
            ->orderBy('created_at', 'desc');

        // Apply status filter if provided
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date range filter if provided
        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Update order status.
     */
    public function updateOrderStatus(Order $order, OrderStatus $status): Order
    {
        $order->update(['status' => $status]);

        Log::info('Order status updated', [
            'order_id' => $order->id,
            'old_status' => $order->getOriginal('status'),
            'new_status' => $status->value,
        ]);

        return $order->fresh();
    }

    /**
     * Process order completion.
     */
    public function completeOrder(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            // Mark order as completed
            $order = $this->updateOrderStatus($order, OrderStatus::COMPLETED);

            // Assign membership plan to user
            if ($order->membershipPlan) {
                $this->assignMembershipToUser($order->user, $order->membershipPlan);
            }

            Log::info('Order completed successfully', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'membership_plan_id' => $order->membership_plan_id,
            ]);

            return $order;
        });
    }

    /**
     * Validate membership plan availability.
     */
    private function validateMembershipPlan(int|string $membershipPlanId): MembershipPlan
    {
        $plan = MembershipPlan::query()->where('id', $membershipPlanId)
            ->where('is_active', true)
            ->first();

        if (!$plan) {
            throw new ModelNotFoundException(__('messages.error.membership_plan_not_found'));
        }

        return $plan;
    }

    /**
     * Calculate discount based on coupon code.
     */
    private function calculateDiscount(float $subtotal, ?string $couponCode): float
    {
        if (!$couponCode) {
            return 0.0;
        }

        // TODO: Implement coupon validation and discount calculation
        // For now, return 0 as no coupon system is implemented
        Log::info('Coupon code provided but not processed', [
            'coupon_code' => $couponCode,
            'subtotal' => $subtotal,
        ]);

        return 0.0;
    }

    /**
     * Assign membership plan to user.
     */
    private function assignMembershipToUser(User $user, MembershipPlan $plan): void
    {
        $expiresAt = match ($plan->billing_cycle) {
            'monthly' => now()->addMonth(),
            'yearly' => now()->addYear(),
            'lifetime' => null,
            default => now()->addMonth(),
        };

        $user->assignMembershipPlan($plan, $expiresAt);

        Log::info('Membership plan assigned to user', [
            'user_id' => $user->id,
            'membership_plan_id' => $plan->id,
            'expires_at' => $expiresAt?->toISOString(),
        ]);
    }
}
