<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'email',
        'payment_method',
        'currency',
        'payment_logs',
        'amount',
        'transaction_id',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'payment_logs' => 'array',
    ];

    /**
     * Get the user that owns this transaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the invoice that this transaction belongs to.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Mark this transaction as completed.
     */
    public function markAsCompleted(?string $transactionId = null): void
    {
        $this->update([
            'status' => 'completed',
            'processed_at' => now(),
            'transaction_id' => $transactionId ?? $this->transaction_id,
        ]);
    }

    /**
     * Mark this transaction as failed.
     */
    public function markAsFailed(?string $reason = null): void
    {
        $logs = $this->payment_logs ?? [];
        if ($reason) {
            $logs[] = [
                'timestamp' => now()->toISOString(),
                'event' => 'failed',
                'reason' => $reason,
            ];
        }

        $this->update([
            'status' => 'failed',
            'processed_at' => now(),
            'payment_logs' => $logs,
        ]);
    }

    /**
     * Add a log entry to the payment logs.
     */
    public function addPaymentLog(string $event, array $data = []): void
    {
        $logs = $this->payment_logs ?? [];
        $logs[] = array_merge([
            'timestamp' => now()->toISOString(),
            'event' => $event,
        ], $data);

        $this->update(['payment_logs' => $logs]);
    }

    /**
     * Scope to filter transactions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter completed transactions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter pending transactions.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to filter failed transactions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter transactions by payment method.
     */
    public function scopeByPaymentMethod($query, string $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    /**
     * Scope to filter transactions for a specific user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter transactions for a specific invoice.
     */
    public function scopeForInvoice($query, $invoiceId)
    {
        return $query->where('invoice_id', $invoiceId);
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        if (! $this->amount) {
            return 'N/A';
        }

        return number_format($this->amount, 2).' '.strtoupper($this->currency);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Check if the transaction is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transaction is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get the latest payment log entry.
     */
    public function getLatestPaymentLogAttribute(): ?array
    {
        $logs = $this->payment_logs ?? [];

        return empty($logs) ? null : end($logs);
    }
}
