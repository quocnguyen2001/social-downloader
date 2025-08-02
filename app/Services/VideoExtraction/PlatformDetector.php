<?php

namespace App\Services\VideoExtraction;

use App\Enums\Platform;

/**
 * Service for detecting video platform from URLs.
 */
class PlatformDetector
{
    /**
     * Platform URL patterns.
     */
    private const array PLATFORM_PATTERNS = [
        Platform::YOUTUBE->value => [
            '/youtube\.com\/watch\?v=/',
            '/youtu\.be\//',
            '/youtube\.com\/embed\//',
            '/youtube\.com\/v\//',
            '/m\.youtube\.com\/watch\?v=/',
        ],
        Platform::TIKTOK->value => [
            '/tiktok\.com\//',
            '/vm\.tiktok\.com\//',
            '/vt\.tiktok\.com\//',
        ],
        Platform::INSTAGRAM->value => [
            '/instagram\.com\/p\//',
            '/instagram\.com\/reel\//',
            '/instagram\.com\/tv\//',
            '/instagr\.am\/p\//',
        ],
        Platform::FACEBOOK->value => [
            '/facebook\.com\/.*\/videos\//',
            '/fb\.watch\//',
            '/facebook\.com\/watch[\/?]/',
            '/facebook\.com\/share\/v\//',
        ],
    ];

    /**
     * Detect platform from URL.
     *
     * @return Platform|null
     */
    public function detectPlatform(string $url): Platform|int|null
    {
        foreach (self::PLATFORM_PATTERNS as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $url)) {
                    return $platform instanceof Platform ? $platform : Platform::tryFrom($platform);
                }
            }
        }

        return null;
    }

    /**
     * Get supported platforms.
     *
     * @return array<Platform>
     */
    public function getSupportedPlatforms(): array
    {
        return array_keys(self::PLATFORM_PATTERNS);
    }

    /**
     * Check if URL is supported.
     */
    public function isSupported(string $url): bool
    {
        return $this->detectPlatform($url) !== null;
    }

    /**
     * Get URL patterns for a platform.
     */
    public function getUrlPatterns(Platform $platform): array
    {
        return self::PLATFORM_PATTERNS[$platform->value] ?? [];
    }
}
