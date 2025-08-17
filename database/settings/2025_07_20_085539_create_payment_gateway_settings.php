<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Payment Gateway Settings Migration.
 *
 * This migration initializes the payment gateway settings
 * with default values for bank transfer and PayPal configurations.
 */
return new class extends SettingsMigration
{
    /**
     * Run the migration to create payment gateway settings.
     */
    public function up(): void
    {
        // Bank Transfer Configuration
        $this->migrator->add('payment_gateway.bank_name', null);
        $this->migrator->add('payment_gateway.bank_account', null);
        $this->migrator->add('payment_gateway.bank_account_number', null);
        $this->migrator->add('payment_gateway.money_transfer_content_template', 'Payment for order #{order_id} - {user_name}');
        $this->migrator->add('payment_gateway.api_transactions_api', null);
        $this->migrator->add('payment_gateway.bank_transfer_enabled', false);
        $this->migrator->add('payment_gateway.bank_transfer_currency', 'VND');

        // PayPal Configuration
        $this->migrator->add('payment_gateway.paypal_client_id', null);
        $this->migrator->add('payment_gateway.paypal_client_secret', null);
        $this->migrator->add('payment_gateway.paypal_environment', 'sandbox');
        $this->migrator->add('payment_gateway.paypal_webhook_id', null);
        $this->migrator->add('payment_gateway.paypal_webhook_secret', null);
        $this->migrator->add('payment_gateway.paypal_enabled', false);
        $this->migrator->add('payment_gateway.paypal_currency', 'USD');
    }

    /**
     * Reverse the migration by removing payment gateway settings.
     */
    public function down(): void
    {
        // Bank Transfer Configuration
        $this->migrator->delete('payment_gateway.bank_name');
        $this->migrator->delete('payment_gateway.bank_account');
        $this->migrator->delete('payment_gateway.bank_account_number');
        $this->migrator->delete('payment_gateway.money_transfer_content_template');
        $this->migrator->delete('payment_gateway.api_transactions_api');
        $this->migrator->delete('payment_gateway.bank_transfer_enabled');
        $this->migrator->delete('payment_gateway.bank_transfer_currency');

        // PayPal Configuration
        $this->migrator->delete('payment_gateway.paypal_client_id');
        $this->migrator->delete('payment_gateway.paypal_client_secret');
        $this->migrator->delete('payment_gateway.paypal_environment');
        $this->migrator->delete('payment_gateway.paypal_webhook_id');
        $this->migrator->delete('payment_gateway.paypal_webhook_secret');
        $this->migrator->delete('payment_gateway.paypal_enabled');
        $this->migrator->delete('payment_gateway.paypal_currency');
    }
};
