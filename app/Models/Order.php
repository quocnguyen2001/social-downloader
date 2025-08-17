<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
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
        'api_key_id',
        'membership_plan_id',
        'billing_month',
        'total_requests',
        'total_cost',
        'invoice_sent',
        'invoice_sent_at',
        'paid',
        'paid_at',
        'payment_method',
    ];

    protected $casts = [
        'billing_month' => 'date',
        'total_cost' => 'decimal:2',
        'invoice_sent' => 'boolean',
        'invoice_sent_at' => 'datetime',
        'paid' => 'boolean',
        'paid_at' => 'datetime',
        'payment_method' => PaymentMethod::class,
    ];

    /**
     * Get the API key that owns this order record.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Get the membership plan associated with this order.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get the transactions for this order.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get all API requests for this billing period.
     */
    public function apiRequests()
    {
        $startOfMonth = $this->billing_month->startOfMonth();
        $endOfMonth = $this->billing_month->copy()->endOfMonth();

        return $this->apiKey->apiRequests()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
    }

    /**
     * Calculate the total cost and request counts from API requests.
     */
    public function calculateTotal(): void
    {
        $startOfMonth = $this->billing_month->startOfMonth();
        $endOfMonth = $this->billing_month->copy()->endOfMonth();

        $requests = $this->apiKey->apiRequests()
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('billed', true)
            ->get();

        $this->total_requests = $requests->count();
        $this->total_cost = $requests->sum('cost');

        $this->save();
    }

    /**
     * Mark this order as paid.
     */
    public function markAsPaid(?string $paymentMethod = null): void
    {
        $this->update([
            'paid' => true,
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
        ]);
    }

    /**
     * Mark this order as unpaid.
     */
    public function markAsUnpaid(): void
    {
        $this->update([
            'paid' => false,
            'paid_at' => null,
            'payment_method' => null,
        ]);
    }

    /**
     * Send order for this billing.
     */
    public function sendOrder(): void
    {
        $this->update([
            'invoice_sent' => true,
            'invoice_sent_at' => now(),
        ]);

        // Here you would implement actual order sending logic
        // For now, we just mark it as sent
    }

    /**
     * Scope to filter paid orders.
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }

    /**
     * Scope to filter unpaid orders.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('paid', false);
    }

    /**
     * Scope to filter orders with sent orders.
     */
    public function scopeOrderSent($query)
    {
        return $query->where('invoice_sent', true);
    }

    /**
     * Scope to filter orders without sent orders.
     */
    public function scopeOrderNotSent($query)
    {
        return $query->where('invoice_sent', false);
    }

    /**
     * Scope to filter orders by month.
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('billing_month', $year)
            ->whereMonth('billing_month', $month);
    }

    /**
     * Scope to filter orders for a specific API key.
     */
    public function scopeForApiKey($query, $apiKeyId)
    {
        return $query->where('api_key_id', $apiKeyId);
    }

    /**
     * Get formatted total cost with currency.
     */
    public function getFormattedTotalCostAttribute(): string
    {
        return number_format($this->total_cost, 2).' VND';
    }

    /**
     * Get the billing month in a readable format.
     */
    public function getFormattedBillingMonthAttribute(): string
    {
        return $this->billing_month->format('F Y');
    }

    /**
     * Get the payment status badge color.
     */
    public function getPaymentStatusBadgeColorAttribute(): string
    {
        return $this->paid ? 'success' : 'danger';
    }

    /**
     * Get the order status badge color.
     */
    public function getOrderStatusBadgeColorAttribute(): string
    {
        return $this->invoice_sent ? 'success' : 'warning';
    }

    /**
     * Check if this order is overdue (unpaid and order sent more than 30 days ago).
     */
    public function isOverdue(): bool
    {
        return ! $this->paid &&
               $this->invoice_sent &&
               $this->invoice_sent_at &&
               $this->invoice_sent_at->diffInDays(now()) > 30;
    }
}
