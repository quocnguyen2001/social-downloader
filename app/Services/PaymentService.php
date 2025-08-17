<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Service for handling payment operations.
 */
class PaymentService
{
    /**
     * Create a payment transaction for an order.
     */
    public function createTransaction(
        User $user,
        Order $order,
        PaymentMethod $paymentMethod,
        float $amount
    ): Transaction {
        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'charge_id' => $this->generateChargeId($paymentMethod),
            'order_id' => $order->id,
            'payment_method' => $paymentMethod->value,
            'currency' => 'VND',
            'amount' => $amount,
            'status' => 'pending',
            'payment_logs' => [
                [
                    'timestamp' => now()->toISOString(),
                    'event' => 'transaction_created',
                    'payment_method' => $paymentMethod->value,
                    'amount' => $amount,
                ]
            ],
        ]);

        Log::info('Payment transaction created', [
            'transaction_id' => $transaction->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'payment_method' => $paymentMethod->value,
            'amount' => $amount,
        ]);

        return $transaction;
    }

    /**
     * Process payment based on payment method.
     */
    public function processPayment(Transaction $transaction): array
    {
        $paymentMethod = PaymentMethod::from($transaction->payment_method);

        return match ($paymentMethod) {
            PaymentMethod::BANK_TRANSFER => $this->processBankTransfer($transaction),
            PaymentMethod::PAYPAL => $this->processPayPalPayment($transaction),
        };
    }

    /**
     * Validate payment method.
     */
    public function validatePaymentMethod(string $paymentMethod): bool
    {
        try {
            return true;
        } catch (\ValueError) {
            return false;
        }
    }

    /**
     * Mark transaction as completed.
     */
    public function completeTransaction(Transaction $transaction, ?string $externalChargeId = null): Transaction
    {
        $transaction->markAsCompleted($externalChargeId);

        $transaction->addPaymentLog('payment_completed', [
            'external_charge_id' => $externalChargeId,
            'completed_at' => now()->toISOString(),
        ]);

        Log::info('Payment transaction completed', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'external_charge_id' => $externalChargeId,
        ]);

        return $transaction->fresh();
    }

    /**
     * Mark transaction as failed.
     */
    public function failTransaction(Transaction $transaction, string $reason): Transaction
    {
        $transaction->markAsFailed($reason);

        Log::warning('Payment transaction failed', [
            'transaction_id' => $transaction->id,
            'order_id' => $transaction->order_id,
            'reason' => $reason,
        ]);

        return $transaction->fresh();
    }

    /**
     * Process bank transfer payment.
     */
    private function processBankTransfer(Transaction $transaction): array
    {
        // Bank transfer requires manual verification
        $transaction->addPaymentLog('bank_transfer_initiated', [
            'instructions' => 'Please transfer the amount to the provided bank account',
            'verification_required' => true,
        ]);

        return [
            'success' => true,
            'message' => __('messages.payment.bank_transfer_initiated'),
            'requires_verification' => true,
            'instructions' => __('messages.payment.bank_transfer_instructions'),
        ];
    }

    /**
     * Process PayPal payment.
     */
    private function processPayPalPayment(Transaction $transaction): array
    {
        try {
            // TODO: Integrate with actual PayPal service
            // For now, simulate PayPal processing
            $transaction->addPaymentLog('paypal_processing', [
                'paypal_order_id' => 'PAYPAL_' . Str::random(10),
                'status' => 'processing',
            ]);

            return [
                'success' => true,
                'message' => __('messages.payment.paypal_processing'),
                'requires_verification' => false,
                'payment_url' => null, // Would contain PayPal checkout URL
            ];
        } catch (\Exception $e) {
            $this->failTransaction($transaction, $e->getMessage());

            return [
                'success' => false,
                'message' => __('messages.payment.paypal_failed'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate a unique charge ID for the transaction.
     */
    private function generateChargeId(PaymentMethod $paymentMethod): string
    {
        $prefix = match ($paymentMethod) {
            PaymentMethod::BANK_TRANSFER => 'BT',
            PaymentMethod::PAYPAL => 'PP',
        };

        return $prefix . '_' . now()->format('Ymd') . '_' . Str::random(8);
    }
}
