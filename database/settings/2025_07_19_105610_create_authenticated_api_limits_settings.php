<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Authenticated API Limits Settings Migration.
 *
 * This migration initializes the API limitations and restrictions
 * for authenticated users without subscription packages.
 */
return new class extends SettingsMigration
{
    /**
     * Run the migration to create authenticated API limits settings.
     */
    public function up(): void
    {
        $this->migrator->add('authenticated_api_limits.daily_request_limit', 50);
        $this->migrator->add('authenticated_api_limits.hourly_request_limit', 20);
        $this->migrator->add('authenticated_api_limits.allowed_platforms', ['youtube', 'tiktok', 'instagram']);
        $this->migrator->add('authenticated_api_limits.allowed_qualities', ['360p', '480p', '720p']);
        $this->migrator->add('authenticated_api_limits.allowed_formats', ['mp4', 'mp3']);
        $this->migrator->add('authenticated_api_limits.rate_limit_per_minute', 10);
    }

    /**
     * Reverse the migration by removing authenticated API limits settings.
     */
    public function down(): void
    {
        $this->migrator->delete('authenticated_api_limits.daily_request_limit');
        $this->migrator->delete('authenticated_api_limits.hourly_request_limit');
        $this->migrator->delete('authenticated_api_limits.allowed_platforms');
        $this->migrator->delete('authenticated_api_limits.allowed_qualities');
        $this->migrator->delete('authenticated_api_limits.allowed_formats');
        $this->migrator->delete('authenticated_api_limits.rate_limit_per_minute');
    }
};
