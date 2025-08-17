<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Payment Gateway Settings for the application.
 *
 * This class manages payment gateway configurations including
 * bank transfer settings and PayPal integration settings.
 */
class PaymentGatewaySettings extends Settings
{
    // Bank Transfer Configuration
    /**
     * Selected bank name from VietQR API.
     */
    public ?string $bank_name;

    /**
     * Bank account holder name.
     */
    public ?string $bank_account;

    /**
     * Bank account number.
     */
    public ?string $bank_account_number;

    /**
     * Template for money transfer content/description.
     */
    public ?string $money_transfer_content_template;

    /**
     * API endpoint for transaction verification.
     */
    public ?string $api_transactions_api;

    // PayPal Configuration
    /**
     * PayPal client ID.
     */
    public ?string $paypal_client_id;

    /**
     * PayPal client secret.
     */
    public ?string $paypal_client_secret;

    /**
     * PayPal environment (sandbox or live).
     */
    public string $paypal_environment;

    /**
     * PayPal webhook ID for payment notifications.
     */
    public ?string $paypal_webhook_id;

    /**
     * PayPal webhook secret for signature verification.
     */
    public ?string $paypal_webhook_secret;

    /**
     * Enable/disable PayPal payment method.
     */
    public bool $paypal_enabled;

    /**
     * Enable/disable bank transfer payment method.
     */
    public bool $bank_transfer_enabled;

    /**
     * PayPal currency code.
     */
    public string $paypal_currency;

    /**
     * Bank transfer currency code.
     */
    public string $bank_transfer_currency;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'payment_gateway';
    }

    /**
     * Get default values for settings.
     */
    public static function defaults(): array
    {
        return [
            // Bank Transfer defaults
            'bank_name' => null,
            'bank_account' => null,
            'bank_account_number' => null,
            'money_transfer_content_template' => 'Payment for order #{order_id} - {user_name}',
            'api_transactions_api' => null,
            'bank_transfer_enabled' => false,
            'bank_transfer_currency' => 'VND',

            // PayPal defaults
            'paypal_client_id' => null,
            'paypal_client_secret' => null,
            'paypal_environment' => 'sandbox',
            'paypal_webhook_id' => null,
            'paypal_webhook_secret' => null,
            'paypal_enabled' => false,
            'paypal_currency' => 'USD',
        ];
    }

    /**
     * Get available PayPal environment options.
     */
    public static function getPayPalEnvironmentOptions(): array
    {
        return [
            'sandbox' => 'Sandbox (Testing)',
            'live' => 'Live (Production)',
        ];
    }

    /**
     * Get available currency options for PayPal.
     */
    public static function getPayPalCurrencyOptions(): array
    {
        return [
            'USD' => 'US Dollar (USD)',
            'EUR' => 'Euro (EUR)',
            'GBP' => 'British Pound (GBP)',
            'CAD' => 'Canadian Dollar (CAD)',
            'AUD' => 'Australian Dollar (AUD)',
            'JPY' => 'Japanese Yen (JPY)',
        ];
    }

    /**
     * Get available currency options for bank transfer.
     */
    public static function getBankTransferCurrencyOptions(): array
    {
        return [
            'VND' => 'Vietnamese Dong (VND)',
            'USD' => 'US Dollar (USD)',
        ];
    }

    /**
     * Check if bank transfer is properly configured.
     */
    public function isBankTransferConfigured(): bool
    {
        return $this->bank_transfer_enabled &&
               ! empty($this->bank_name) &&
               ! empty($this->bank_account) &&
               ! empty($this->bank_account_number);
    }

    /**
     * Check if PayPal is properly configured.
     */
    public function isPayPalConfigured(): bool
    {
        return $this->paypal_enabled &&
               ! empty($this->paypal_client_id) &&
               ! empty($this->paypal_client_secret);
    }

    /**
     * Get configured payment methods.
     */
    public function getConfiguredPaymentMethods(): array
    {
        $methods = [];

        if ($this->isBankTransferConfigured()) {
            $methods['bank_transfer'] = 'Bank Transfer';
        }

        if ($this->isPayPalConfigured()) {
            $methods['paypal'] = 'PayPal';
        }

        return $methods;
    }

    /**
     * Get bank transfer content with placeholders replaced.
     */
    public function getBankTransferContent(array $placeholders = []): string
    {
        $template = $this->money_transfer_content_template ?? 'Payment for order #{order_id} - {user_name}';

        foreach ($placeholders as $key => $value) {
            $template = str_replace('{'.$key.'}', $value, $template);
        }

        return $template;
    }
}
