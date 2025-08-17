<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Settings\PaymentGatewaySettings;
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
     * Validate payment method against available configured methods.
     */
    public function validatePaymentMethod(string $paymentMethod): bool
    {
        try {
            // Check if the payment method is a valid enum value
            $method = PaymentMethod::from($paymentMethod);

            // Check if the payment method is available/configured
            $availableMethods = $this->getAvailablePaymentMethods();

            return array_key_exists($paymentMethod, $availableMethods);
        } catch (\ValueError) {
            return false;
        }
    }

    /**
     * Get available payment methods based on configuration.
     *
     * @return array Array of available payment methods with their labels
     */
    public function getAvailablePaymentMethods(): array
    {
        $settings = app(PaymentGatewaySettings::class);

        return $settings->getConfiguredPaymentMethods();
    }

    /**
     * Get available payment method values only.
     *
     * @return array Array of available payment method values
     */
    public function getAvailablePaymentMethodValues(): array
    {
        return array_keys($this->getAvailablePaymentMethods());
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
        try {
            $settings = app(PaymentGatewaySettings::class);
            $vietQRService = app(VietQRService::class);

            // Check if bank transfer is properly configured
            if (!$settings->isBankTransferConfigured()) {
                throw new \Exception(__('messages.payment.bank_transfer_not_configured'));
            }

            // Get order and user information for transfer content
            $order = $transaction->order;
            $user = $transaction->user;

            // Generate transfer content with placeholders
            $transferContent = $settings->getBankTransferContent([
                'order_id' => $order->id,
                'user_name' => $user->name,
                'transaction_id' => $transaction->id,
            ]);

            // Extract bank code from bank name (format: "VCB - Vietcombank")
            $bankCode = explode(' - ', $settings->bank_name)[0] ?? 'VCB';

            // Generate QR code URL
            $qrCodeUrl = $vietQRService->getQRCodeImageURL(
                bankId: $bankCode,
                accountNo: $settings->bank_account_number,
                template: 'compact2',
                amount: (float) $transaction->amount,
                description: $transferContent,
                accountName: $settings->bank_account
            );

            // Generate bank logo URL
            $bankLogoUrl = $vietQRService->getBankLogoImageURL($bankCode);

            // Prepare bank information
            $bankInfo = [
                'bank_name' => $settings->bank_name,
                'bank_logo' => $bankLogoUrl,
                'account_number' => $settings->bank_account_number,
                'account_holder' => $settings->bank_account,
                'transfer_content' => $transferContent,
                'qr_code' => $qrCodeUrl,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ];

            // Log bank transfer initiation
            $transaction->addPaymentLog('bank_transfer_initiated', [
                'bank_info' => $bankInfo,
                'verification_required' => true,
            ]);

            return [
                'success' => true,
                'message' => __('messages.payment.bank_transfer_initiated'),
                'requires_verification' => true,
                'bank_info' => $bankInfo,
            ];

        } catch (\Exception $e) {
            Log::error('Bank transfer processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => __('messages.payment.bank_transfer_failed'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process PayPal payment.
     */
    private function processPayPalPayment(Transaction $transaction): array
    {
        try {
            $settings = app(PaymentGatewaySettings::class);
            $paypalService = app(PayPalService::class);

            // Check if PayPal is properly configured
            if (!$settings->isPayPalConfigured()) {
                throw new \Exception(__('messages.payment.paypal_not_configured'));
            }

            // Get order and user information
            $order = $transaction->order;
            $user = $transaction->user;

            // Prepare PayPal order data
            $orderData = [
                'reference_id' => $transaction->id,
                'amount' => $transaction->amount,
                'currency' => $settings->paypal_currency,
                'description' => "Payment for Order #{$order->id}",
                'brand_name' => config('app.name'),
                'cancel_url' => url("/payment/cancel/{$transaction->id}"),
                'return_url' => url("/payment/success/{$transaction->id}"),
            ];

            // Create PayPal order
            $paypalResult = $paypalService->createOrder($orderData);

            if (!$paypalResult['success']) {
                throw new \Exception($paypalResult['error'] ?? 'PayPal order creation failed');
            }

            // Log PayPal order creation
            $transaction->addPaymentLog('paypal_order_created', [
                'paypal_order_id' => $paypalResult['paypal_order_id'],
                'status' => $paypalResult['status'],
                'checkout_url' => $paypalResult['checkout_url'],
            ]);

            return [
                'success' => true,
                'message' => __('messages.payment.paypal_checkout_created'),
                'requires_verification' => false,
                'checkout_url' => $paypalResult['checkout_url'],
                'paypal_order_id' => $paypalResult['paypal_order_id'],
            ];

        } catch (\Exception $e) {
            Log::error('PayPal payment processing failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

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
