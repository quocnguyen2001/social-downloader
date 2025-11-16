<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionActivatedEvent;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Throwable;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'customer_name',
        'customer_email',
        'charge_id',
        'subscription_id',
        'payment_method',
        'currency',
        'payment_logs',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_logs' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::updated(function (Transaction $transaction) {
            if ($transaction->isDirty('status') && $transaction->status === 'completed') {
                $transaction->handleSubscriptionActivation();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function markAsCompleted(?string $chargeId = null): void
    {
        $this->update([
            'status' => 'completed',
            'charge_id' => $chargeId ?? $this->charge_id,
        ]);
    }

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
            'payment_logs' => $logs,
        ]);
    }

    public function addPaymentLog(string $event, array $data = []): void
    {
        $logs = $this->payment_logs ?? [];
        $logs[] = array_merge([
            'timestamp' => now()->toISOString(),
            'event' => $event,
        ], $data);

        $this->update(['payment_logs' => $logs]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByPaymentMethod($query, string $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSubscription($query, $subscriptionId)
    {
        return $query->where('subscription_id', $subscriptionId);
    }

    public function getFormattedAmountAttribute(): string
    {
        if (! $this->amount) {
            return 'N/A';
        }

        return format_currency((float) $this->amount, strtoupper($this->currency));
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            default => 'gray',
        };
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function getLatestPaymentLogAttribute(): ?array
    {
        $logs = $this->payment_logs ?? [];

        return empty($logs) ? null : end($logs);
    }

    protected function handleSubscriptionActivation(): void
    {
        try {
            $subscription = $this->subscription;

            if (! $subscription) {
                Log::warning('Transaction completed but no associated subscription found', [
                    'transaction_id' => $this->id,
                ]);

                return;
            }

            $subscription->update(['status' => SubscriptionStatus::COMPLETED]);

            if ($subscription->completed_at === null) {
                $subscription->update(['completed_at' => now()]);

                SubscriptionActivatedEvent::dispatch($subscription, [
                    'transaction_id' => $this->id,
                    'completed_via' => 'transaction_status_change',
                ]);

                Log::info('Subscription marked as completed and event dispatched', [
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $this->id,
                    'user_id' => $subscription->user_id,
                ]);
            } else {
                Log::info('Subscription already completed, skipping event dispatch', [
                    'subscription_id' => $subscription->id,
                    'transaction_id' => $this->id,
                    'completed_at' => $subscription->completed_at->toISOString(),
                ]);
            }

        } catch (Throwable $exception) {
            Log::error('Failed to handle subscription activation', [
                'transaction_id' => $this->id,
                'exception' => $exception->getMessage(),
                'exception_trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }
}
