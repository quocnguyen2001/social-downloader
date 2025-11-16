<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'membership_plan_id',
        'payment_id',
        'total',
        'subtotal',
        'discount',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'status' => SubscriptionStatus::class,
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'payment_id');
    }

    public function calculateTotal(): void
    {
        $this->total = $this->subtotal - $this->discount;
        $this->save();
    }

    public function applyDiscount(float $discountAmount): void
    {
        $this->discount = $discountAmount;
        $this->calculateTotal();
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => SubscriptionStatus::PROCESSING]);
    }

    public function markAsCompleted(): void
    {
        $this->update(['status' => SubscriptionStatus::COMPLETED]);
    }

    public function markAsPending(): void
    {
        $this->update(['status' => SubscriptionStatus::PENDING]);
    }

    public function scopeWithStatus($query, SubscriptionStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('status', SubscriptionStatus::PENDING);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', SubscriptionStatus::PROCESSING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', SubscriptionStatus::COMPLETED);
    }

    public function scopeForMembershipPlan($query, $membershipPlanId)
    {
        return $query->where('membership_plan_id', $membershipPlanId);
    }

    public function getFormattedTotalAttribute(): string
    {
        return format_currency((float) $this->total, 'VND');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return format_currency((float) $this->subtotal, 'VND');
    }

    public function getFormattedDiscountAttribute(): string
    {
        return format_currency((float) $this->discount, 'VND');
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return $this->status->getColor();
    }

    public function hasDiscount(): bool
    {
        return $this->discount > 0;
    }

    public function getDiscountPercentage(): float
    {
        if ($this->subtotal <= 0) {
            return 0;
        }

        return ($this->discount / $this->subtotal) * 100;
    }

    public function isCompleted(): bool
    {
        return $this->status === SubscriptionStatus::COMPLETED && $this->completed_at !== null;
    }

    public function getFormattedCompletedAtAttribute(): ?string
    {
        return $this->completed_at?->format('Y-m-d H:i:s');
    }
}
