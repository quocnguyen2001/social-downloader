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
     * SEO meta title.
     */
    public ?string $meta_title;

    /**
     * SEO meta description.
     */
    public ?string $meta_description;

    /**
     * SEO meta keywords.
     */
    public ?string $meta_keywords;

    /**
     * Copyright text.
     */
    public ?string $copyright_text;

    /**
     * Copyright year.
     */
    public ?string $copyright_year;

    /**
     * Logo file path.
     */
    public ?string $logo_path;

    /**
     * Favicon file path.
     */
    public ?string $favicon_path;

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
            'meta_title' => 'Social Downloader - Download Videos from Social Platforms',
            'meta_description' => 'Download videos and media from popular social platforms like YouTube, TikTok, Instagram, and Facebook with our easy-to-use social downloader tool.',
            'meta_keywords' => 'video downloader, social media downloader, youtube downloader, tiktok downloader, instagram downloader',
            'copyright_text' => 'All rights reserved.',
            'copyright_year' => (string) date('Y'),
            'logo_path' => null,
            'favicon_path' => null,
        ];
    }
}
