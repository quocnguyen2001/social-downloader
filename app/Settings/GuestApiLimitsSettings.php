<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Guest User API Limits Settings.
 *
 * This class manages API restrictions and limitations for
 * unauthenticated users (guests).
 */
class GuestApiLimitsSettings extends Settings
{
    /**
     * Maximum number of API calls per day for guest users.
     */
    public int $daily_request_limit;

    /**
     * Maximum number of API calls per hour for guest users.
     */
    public int $hourly_request_limit;

    /**
     * Allowed platforms for guest users.
     * Array of platform names that guests can access.
     */
    public array $allowed_platforms;

    /**
     * Available quality options for guest users.
     * Array of quality settings guests can request.
     */
    public array $allowed_qualities;

    /**
     * Allowed file formats for guest users.
     * Array of file types guests can download.
     */
    public array $allowed_formats;

    /**
     * Rate limit per minute for guest users.
     */
    public int $rate_limit_per_minute;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'guest_api_limits';
    }

    /**
     * Get default values for settings.
     */
    public static function defaults(): array
    {
        return [
            'daily_request_limit' => 10,
            'hourly_request_limit' => 5,
            'allowed_platforms' => ['youtube', 'tiktok'],
            'allowed_qualities' => ['360p', '480p'],
            'allowed_formats' => ['mp4'],
            'rate_limit_per_minute' => 2,
            'max_concurrent_downloads' => 1,
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
