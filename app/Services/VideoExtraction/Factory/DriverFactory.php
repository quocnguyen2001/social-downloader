<?php

namespace App\Services\VideoExtraction\Factory;

use App\Enums\Platform;
use App\Services\VideoExtraction\Contracts\DriverInterface;
use App\Services\VideoExtraction\Drivers\FacebookDriver;
use App\Services\VideoExtraction\Drivers\InstagramDriver;
use App\Services\VideoExtraction\Drivers\TikTokDriver;
use App\Services\VideoExtraction\Drivers\YouTubeDriver;
use App\Services\VideoExtraction\Exceptions\UnsupportedPlatformException;
use App\Services\VideoExtraction\Registry\DriverRegistry;
use Illuminate\Support\Facades\Log;

/**
 * Factory for creating video extraction drivers.
 *
 * This factory automatically detects the platform from a URL
 * and returns the appropriate driver instance.
 */
class DriverFactory
{
    /**
     * The driver registry instance.
     */
    private DriverRegistry $registry;

    /**
     * Create a new driver factory instance.
     */
    public function __construct(?DriverRegistry $registry = null)
    {
        $this->registry = $registry ?? new DriverRegistry;
        $this->registerDefaultDrivers();
    }

    /**
     * Create a driver for the given URL.
     *
     * @param  string  $url  The URL to create a driver for
     * @param  array  $config  Optional driver configuration
     * @return DriverInterface The appropriate driver instance
     *
     * @throws UnsupportedPlatformException If no driver supports the URL
     */
    public function create(string $url, array $config = []): DriverInterface
    {
        Log::info('Creating driver for URL', ['url' => $url]);

        $platform = $this->detectPlatform($url);

        if (! $platform) {
            throw new UnsupportedPlatformException($url);
        }

        $driver = $this->registry->get($platform, $config);

        Log::info('Driver created successfully', [
            'url' => $url,
            'platform' => $platform->value,
            'driver' => get_class($driver),
        ]);

        return $driver;
    }

    /**
     * Create a driver for a specific platform.
     *
     * @param  Platform  $platform  The platform to create a driver for
     * @param  array  $config  Optional driver configuration
     * @return DriverInterface The driver instance
     */
    public function createForPlatform(Platform $platform, array $config = []): DriverInterface
    {
        return $this->registry->get($platform, $config);
    }

    /**
     * Detect the platform from a URL.
     *
     * @param  string  $url  The URL to analyze
     * @return int|null The detected platform or null if not supported
     */
    public function detectPlatform(string $url): ?Platform
    {
        // Normalize URL for consistent matching
        $url = strtolower(trim($url));

        // Remove protocol for easier matching
        $urlWithoutProtocol = preg_replace('/^https?:\/\//', '', $url);

        // Platform detection patterns
        $platformPatterns = [
            Platform::YOUTUBE->value => [
                'youtube.com',
                'youtu.be',
                'm.youtube.com',
                'www.youtube.com',
            ],
            Platform::TIKTOK->value => [
                'tiktok.com',
                'vm.tiktok.com',
                'm.tiktok.com',
                'www.tiktok.com',
            ],
            Platform::INSTAGRAM->value => [
                'instagram.com',
                'instagr.am',
                'www.instagram.com',
            ],
            Platform::FACEBOOK->value => [
                'facebook.com',
                'fb.watch',
                'm.facebook.com',
                'www.facebook.com',
            ],
        ];

        foreach ($platformPatterns as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($urlWithoutProtocol, $pattern)) {
                    Log::debug('Platform detected', [
                        'url' => $url,
                        'platform' => $platform,
                        'pattern' => $pattern,
                    ]);

                    return Platform::tryFrom($platform);
                }
            }
        }

        Log::warning('No platform detected for URL', ['url' => $url]);

        return null;
    }

    /**
     * Check if a URL is supported by any driver.
     *
     * @param  string  $url  The URL to check
     * @return bool True if the URL is supported
     */
    public function isSupported(string $url): bool
    {
        return $this->detectPlatform($url) !== null;
    }

    /**
     * Get all available drivers.
     *
     * @return array<Platform, string> Array of platform => driver class mappings
     */
    public function getAvailableDrivers(): array
    {
        return $this->registry->getAllDriverClasses();
    }

    /**
     * Get all supported platforms.
     *
     * @return array<Platform> Array of supported platforms
     */
    public function getSupportedPlatforms(): array
    {
        return $this->registry->getSupportedPlatforms();
    }

    /**
     * Register a custom driver.
     *
     * @param  Platform  $platform  The platform this driver handles
     * @param  string  $driverClass  The driver class name
     */
    public function registerDriver(Platform $platform, string $driverClass): self
    {
        $this->registry->register($platform, $driverClass);

        return $this;
    }

    /**
     * Check if a platform has a registered driver.
     *
     * @param  Platform  $platform  The platform to check
     * @return bool True if a driver is registered for the platform
     */
    public function hasDriver(Platform $platform): bool
    {
        return $this->registry->has($platform);
    }

    /**
     * Get detailed information about URL support.
     *
     * @param  string  $url  The URL to analyze
     * @return array Information about URL support
     */
    public function analyzeUrl(string $url): array
    {
        $platform = $this->detectPlatform($url);

        $analysis = [
            'url' => $url,
            'is_supported' => $platform !== null,
            'detected_platform' => $platform?->value,
            'driver_available' => $platform ? $this->hasDriver($platform) : false,
        ];

        if ($platform && $this->hasDriver($platform)) {
            $driver = $this->registry->get($platform);
            $analysis['driver_class'] = get_class($driver);
            $analysis['supported_qualities'] = array_map(
                fn ($quality) => $quality->value,
                $driver->getSupportedQualities()
            );
            $analysis['supported_formats'] = array_map(
                fn ($format) => $format->value,
                $driver->getSupportedFormats()
            );
            $analysis['url_patterns'] = $driver->getUrlPatterns();
        }

        return $analysis;
    }

    /**
     * Register the default drivers.
     */
    private function registerDefaultDrivers(): void
    {
        $this->registry->register(Platform::YOUTUBE, YouTubeDriver::class);
        $this->registry->register(Platform::TIKTOK, TikTokDriver::class);
        $this->registry->register(Platform::INSTAGRAM, InstagramDriver::class);
        $this->registry->register(Platform::FACEBOOK, FacebookDriver::class);
    }
}
