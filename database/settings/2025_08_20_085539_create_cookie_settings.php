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
        $this->migrator->add('cookie.cookie_file_path', null);
    }

    /**
     * Reverse the migration by removing payment gateway settings.
     */
    public function down(): void
    {
        // Bank Transfer Configuration
        $this->migrator->delete('cookie.cookie_file_path');
    }
};
