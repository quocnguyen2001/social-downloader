<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Authenticated User API Limits Settings (No Package).
 *
 * This class manages API restrictions and limitations for
 * logged-in users who don't have subscription packages.
 */
class AuthenticatedApiLimitsSettings extends Settings
{
    /**
     * Maximum number of API calls per day for authenticated users without packages.
     */
    public int $daily_request_limit;

    /**
     * Maximum number of API calls per hour for authenticated users without packages.
     */
    public int $hourly_request_limit;

    /**
     * Allowed platforms for authenticated users without packages.
     * Array of platform names that authenticated users can access.
     */
    public array $allowed_platforms;

    /**
     * Available quality options for authenticated users without packages.
     * Array of quality settings authenticated users can request.
     */
    public array $allowed_qualities;

    /**
     * Allowed file formats for authenticated users without packages.
     * Array of file types authenticated users can download.
     */
    public array $allowed_formats;

    /**
     * Maximum file size in MB that authenticated users without packages can download.
     */
    public int $max_file_size_mb;

    /**
     * Rate limit per minute for authenticated users without packages.
     */
    public int $rate_limit_per_minute;

    /**
     * Enable/disable CAPTCHA for authenticated user requests.
     */
    public bool $require_captcha;

    /**
     * Maximum concurrent downloads for authenticated users without packages.
     */
    public int $max_concurrent_downloads;

    /**
     * Enable/disable priority processing for authenticated users.
     */
    public bool $enable_priority_processing;

    /**
     * Enable/disable bulk downloads for authenticated users.
     */
    public bool $enable_bulk_downloads;

    /**
     * Maximum number of URLs in a bulk download request.
     */
    public int $max_bulk_urls;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'authenticated_api_limits';
    }

    /**
     * Get default values for settings.
     */
    public static function defaults(): array
    {
        return [
            'daily_request_limit' => 50,
            'hourly_request_limit' => 20,
            'allowed_platforms' => ['youtube', 'tiktok', 'instagram'],
            'allowed_qualities' => ['360p', '480p', '720p'],
            'allowed_formats' => ['mp4', 'mp3'],
            'max_file_size_mb' => 200,
            'rate_limit_per_minute' => 10,
        ];
    }

    /**
     * Get available platform options.
     */
    public static function getPlatformOptions(): array
    {
        return [
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
        ];
    }

    /**
     * Get available quality options.
     */
    public static function getQualityOptions(): array
    {
        return [
            '144p' => '144p (Low)',
            '240p' => '240p (Low)',
            '360p' => '360p (Medium)',
            '480p' => '480p (Medium)',
            '720p' => '720p (HD)',
            '1080p' => '1080p (Full HD)',
        ];
    }

    /**
     * Get available format options.
     */
    public static function getFormatOptions(): array
    {
        return [
            'mp4' => 'MP4 (Video)',
            'mp3' => 'MP3 (Audio)',
            'webm' => 'WebM (Video)',
            'wav' => 'WAV (Audio)',
        ];
    }
}
