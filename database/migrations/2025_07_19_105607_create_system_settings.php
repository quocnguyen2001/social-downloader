<?php

use App\Settings\GeneralSettings;
use App\Settings\GuestApiLimitsSettings;
use App\Settings\AuthenticatedApiLimitsSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Initialize default settings for all settings classes
        $this->initializeSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove settings from the settings table
        $this->removeSettings();
    }

    /**
     * Initialize default settings.
     */
    private function initializeSettings(): void
    {
        // Initialize settings by inserting directly into the settings table
        $settings = [
            // General Settings
            ['group' => 'general', 'name' => 'site_name', 'payload' => json_encode('Social Downloader'), 'locked' => false],
            ['group' => 'general', 'name' => 'site_description', 'payload' => json_encode('Download videos and media from social platforms'), 'locked' => false],
            ['group' => 'general', 'name' => 'admin_email', 'payload' => json_encode('admin@example.com'), 'locked' => false],
            ['group' => 'general', 'name' => 'support_email', 'payload' => json_encode('support@example.com'), 'locked' => false],
            ['group' => 'general', 'name' => 'site_url', 'payload' => json_encode(config('app.url', 'http://localhost')), 'locked' => false],
            ['group' => 'general', 'name' => 'allow_user_registration', 'payload' => json_encode(true), 'locked' => false],
            ['group' => 'general', 'name' => 'require_email_verification', 'payload' => json_encode(false), 'locked' => false],
            ['group' => 'general', 'name' => 'default_timezone', 'payload' => json_encode('UTC'), 'locked' => false],
            ['group' => 'general', 'name' => 'maintenance_mode', 'payload' => json_encode(false), 'locked' => false],
            ['group' => 'general', 'name' => 'maintenance_message', 'payload' => json_encode('We are currently performing maintenance. Please check back later.'), 'locked' => false],
            ['group' => 'general', 'name' => 'terms_of_service_url', 'payload' => json_encode(null), 'locked' => false],
            ['group' => 'general', 'name' => 'privacy_policy_url', 'payload' => json_encode(null), 'locked' => false],

            // Guest API Limits Settings
            ['group' => 'guest_api_limits', 'name' => 'daily_request_limit', 'payload' => json_encode(10), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'hourly_request_limit', 'payload' => json_encode(5), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'allowed_platforms', 'payload' => json_encode(['youtube', 'tiktok']), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'allowed_qualities', 'payload' => json_encode(['360p', '480p']), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'allowed_formats', 'payload' => json_encode(['mp4']), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'max_file_size_mb', 'payload' => json_encode(50), 'locked' => false],
            ['group' => 'guest_api_limits', 'name' => 'rate_limit_per_minute', 'payload' => json_encode(2), 'locked' => false],

            // Authenticated API Limits Settings
            ['group' => 'authenticated_api_limits', 'name' => 'daily_request_limit', 'payload' => json_encode(50), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'hourly_request_limit', 'payload' => json_encode(20), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'allowed_platforms', 'payload' => json_encode(['youtube', 'tiktok', 'instagram']), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'allowed_qualities', 'payload' => json_encode(['360p', '480p', '720p']), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'allowed_formats', 'payload' => json_encode(['mp4', 'mp3']), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'max_file_size_mb', 'payload' => json_encode(200), 'locked' => false],
            ['group' => 'authenticated_api_limits', 'name' => 'rate_limit_per_minute', 'payload' => json_encode(10), 'locked' => false],
        ];

        foreach ($settings as $setting) {
            \DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Remove settings from the settings table.
     */
    private function removeSettings(): void
    {
        // Delete settings by group
        \DB::table('settings')->whereIn('group', [
            'general',
            'guest_api_limits',
            'authenticated_api_limits',
        ])->delete();
    }
};
