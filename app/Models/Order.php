<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'payment_id',
        'total',
        'subtotal',
        'discount',
        'status',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'status' => OrderStatus::class,
    ];

    /**
     * Get the user that owns this order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the membership plan associated with this order.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get the transaction associated with this order.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_id');
    }



    /**
     * Calculate the total from subtotal and discount.
     */
    public function calculateTotal(): void
    {
        $this->total = $this->subtotal - $this->discount;
        $this->save();
    }

    /**
     * Apply a discount to the order.
     */
    public function applyDiscount(float $discountAmount): void
    {
        $this->discount = $discountAmount;
        $this->calculateTotal();
    }

    /**
     * Mark this order as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update(['status' => OrderStatus::PROCESSING]);
    }

    /**
     * Mark this order as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => OrderStatus::COMPLETED]);
    }

    /**
     * Mark this order as pending.
     */
    public function markAsPending(): void
    {
        $this->update(['status' => OrderStatus::PENDING]);
    }

    /**
     * Scope to filter orders by status.
     */
    public function scopeWithStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending orders.
     */
    public function scopePending($query)
    {
        return $query->where('status', OrderStatus::PENDING);
    }

    /**
     * Scope to filter processing orders.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', OrderStatus::PROCESSING);
    }

    /**
     * Scope to filter completed orders.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', OrderStatus::COMPLETED);
    }

    /**
     * Scope to filter orders for a specific membership plan.
     */
    public function scopeForMembershipPlan($query, $membershipPlanId)
    {
        return $query->where('membership_plan_id', $membershipPlanId);
    }

    /**
     * Get formatted total with currency.
     */
    public function getFormattedTotalAttribute(): string
    {
        return number_format((float) $this->total, 2).' VND';
    }

    /**
     * Get formatted subtotal with currency.
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return number_format((float) $this->subtotal, 2).' VND';
    }

    /**
     * Get formatted discount with currency.
     */
    public function getFormattedDiscountAttribute(): string
    {
        return number_format((float) $this->discount, 2).' VND';
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return $this->status->getColor();
    }

    /**
     * Check if this order has a discount applied.
     */
    public function hasDiscount(): bool
    {
        return $this->discount > 0;
    }

    /**
     * Get the discount percentage if applicable.
     */
    public function getDiscountPercentage(): float
    {
        if ($this->subtotal <= 0) {
            return 0;
        }

        return ($this->discount / $this->subtotal) * 100;
    }
}
