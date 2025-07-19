<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MonthlyBilling extends Model
{
    /** @use HasFactory<\Database\Factories\MonthlyBillingFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'api_key_id',
        'billing_month',
        'total_requests',
        'total_cost',
        'youtube_requests',
        'tiktok_requests',
        'instagram_requests',
        'facebook_requests',
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
     * Get the API key that owns this billing record.
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
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

        // Calculate platform breakdown
        $this->youtube_requests = $requests->where('platform', 'youtube')->count();
        $this->tiktok_requests = $requests->where('platform', 'tiktok')->count();
        $this->instagram_requests = $requests->where('platform', 'instagram')->count();
        $this->facebook_requests = $requests->where('platform', 'facebook')->count();

        $this->save();
    }

    /**
     * Mark this billing as paid.
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
     * Mark this billing as unpaid.
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
     * Send invoice for this billing.
     */
    public function sendInvoice(): void
    {
        $this->update([
            'invoice_sent' => true,
            'invoice_sent_at' => now(),
        ]);

        // Here you would implement actual invoice sending logic
        // For now, we just mark it as sent
    }

    /**
     * Scope to filter paid billings.
     */
    public function scopePaid($query)
    {
        return $query->where('paid', true);
    }

    /**
     * Scope to filter unpaid billings.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('paid', false);
    }

    /**
     * Scope to filter billings with sent invoices.
     */
    public function scopeInvoiceSent($query)
    {
        return $query->where('invoice_sent', true);
    }

    /**
     * Scope to filter billings without sent invoices.
     */
    public function scopeInvoiceNotSent($query)
    {
        return $query->where('invoice_sent', false);
    }

    /**
     * Scope to filter billings by month.
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('billing_month', $year)
                    ->whereMonth('billing_month', $month);
    }

    /**
     * Scope to filter billings for a specific API key.
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
        return number_format($this->total_cost, 2) . ' VND';
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
     * Get the invoice status badge color.
     */
    public function getInvoiceStatusBadgeColorAttribute(): string
    {
        return $this->invoice_sent ? 'success' : 'warning';
    }

    /**
     * Check if this billing is overdue (unpaid and invoice sent more than 30 days ago).
     */
    public function isOverdue(): bool
    {
        return !$this->paid &&
               $this->invoice_sent &&
               $this->invoice_sent_at &&
               $this->invoice_sent_at->diffInDays(now()) > 30;
    }

    /**
     * Get the most popular platform for this billing period.
     */
    public function getMostPopularPlatformAttribute(): string
    {
        $platforms = [
            'youtube' => $this->youtube_requests,
            'tiktok' => $this->tiktok_requests,
            'instagram' => $this->instagram_requests,
            'facebook' => $this->facebook_requests,
        ];

        return array_search(max($platforms), $platforms) ?: 'none';
    }
}
