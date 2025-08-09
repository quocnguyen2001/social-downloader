<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Guest API Limits Settings Migration.
 *
 * This migration initializes the API limitations and restrictions
 * for unauthenticated (guest) users.
 */
return new class extends SettingsMigration
{
    /**
     * Run the migration to create guest API limits settings.
     */
    public function up(): void
    {
        $this->migrator->add('guest_api_limits.daily_request_limit', 10);
        $this->migrator->add('guest_api_limits.hourly_request_limit', 5);
        $this->migrator->add('guest_api_limits.allowed_platforms', ['youtube', 'tiktok']);
        $this->migrator->add('guest_api_limits.allowed_qualities', ['360p', '480p']);
        $this->migrator->add('guest_api_limits.allowed_formats', ['mp4']);
        $this->migrator->add('guest_api_limits.rate_limit_per_minute', 2);
        $this->migrator->add('guest_api_limits.max_concurrent_downloads', 1);
    }

    /**
     * Reverse the migration by removing guest API limits settings.
     */
    public function down(): void
    {
        $this->migrator->delete('guest_api_limits.daily_request_limit');
        $this->migrator->delete('guest_api_limits.hourly_request_limit');
        $this->migrator->delete('guest_api_limits.allowed_platforms');
        $this->migrator->delete('guest_api_limits.allowed_qualities');
        $this->migrator->delete('guest_api_limits.allowed_formats');
        $this->migrator->delete('guest_api_limits.rate_limit_per_minute');
        $this->migrator->delete('guest_api_limits.max_concurrent_downloads');
    }
};
