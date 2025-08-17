<?php

declare(strict_types=1);

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Cookie Settings for the application.
 *
 * This class manages cookie file configuration for authenticated
 * video extraction from platforms that require login credentials.
 */
class CookieSettings extends Settings
{
    /**
     * Cookie file path for authenticated video extraction.
     */
    public ?string $cookie_file_path;

    /**
     * Get the settings group name.
     */
    public static function group(): string
    {
        return 'cookie';
    }

    /**
     * Get default values for settings.
     */
    public static function defaults(): array
    {
        return [
            'cookie_file_path' => null,
        ];
    }
}
