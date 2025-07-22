<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * General Settings for the application.
 *
 * This class manages basic website information and configuration
 * settings that apply site-wide.
 */
class GeneralSettings extends Settings
{
    /**
     * Site name/title.
     */
    public string $site_name;

    /**
     * Site description/tagline.
     */
    public string $site_description;

    /**
     * Administrative contact email.
     */
    public string $admin_email;

    /**
     * Support contact email.
     */
    public string $support_email;

    /**
     * Site URL.
     */
    public string $site_url;


    /**
     * Default timezone for the application.
     */
    public string $default_timezone;

    /**
     * Enable/disable maintenance mode.
     */
    public bool $maintenance_mode;

    /**
     * Maintenance mode message.
     */
    public string $maintenance_message;

    /**
     * Terms of Service URL.
     */
    public ?string $terms_of_service_url;

    /**
     * Privacy Policy URL.
     */
    public ?string $privacy_policy_url;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'general';
    }

    /**
     * Get default values for settings.
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'Social Downloader',
            'site_description' => 'Download videos and media from social platforms',
            'admin_email' => 'admin@example.com',
            'support_email' => 'support@example.com',
            'site_url' => config('app.url', 'http://localhost'),
            'allow_user_registration' => true,
            'require_email_verification' => false,
            'default_timezone' => 'UTC',
            'maintenance_mode' => false,
            'maintenance_message' => 'We are currently performing maintenance. Please check back later.',
            'terms_of_service_url' => null,
            'privacy_policy_url' => null,
        ];
    }
}
