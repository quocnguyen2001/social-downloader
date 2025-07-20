<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * General Settings Migration.
 *
 * This migration initializes the general application settings
 * with default values for site configuration.
 */
return new class extends SettingsMigration
{
    /**
     * Run the migration to create general settings.
     */
    public function up(): void
    {
        $this->migrator->add('general.site_name', 'Social Downloader');
        $this->migrator->add('general.site_description', 'Download videos and media from social platforms');
        $this->migrator->add('general.admin_email', 'admin@example.com');
        $this->migrator->add('general.support_email', 'support@example.com');
        $this->migrator->add('general.site_url', config('app.url', 'http://localhost'));
        $this->migrator->add('general.allow_user_registration', true);
        $this->migrator->add('general.require_email_verification', false);
        $this->migrator->add('general.default_timezone', 'UTC');
        $this->migrator->add('general.maintenance_mode', false);
        $this->migrator->add('general.maintenance_message', 'We are currently performing maintenance. Please check back later.');
        $this->migrator->add('general.terms_of_service_url', null);
        $this->migrator->add('general.privacy_policy_url', null);
    }

    /**
     * Reverse the migration by removing general settings.
     */
    public function down(): void
    {
        $this->migrator->delete('general.site_name');
        $this->migrator->delete('general.site_description');
        $this->migrator->delete('general.admin_email');
        $this->migrator->delete('general.support_email');
        $this->migrator->delete('general.site_url');
        $this->migrator->delete('general.allow_user_registration');
        $this->migrator->delete('general.require_email_verification');
        $this->migrator->delete('general.default_timezone');
        $this->migrator->delete('general.maintenance_mode');
        $this->migrator->delete('general.maintenance_message');
        $this->migrator->delete('general.terms_of_service_url');
        $this->migrator->delete('general.privacy_policy_url');
    }
};
