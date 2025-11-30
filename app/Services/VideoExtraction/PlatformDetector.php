<?php

namespace App\Services\VideoExtraction;

use App\Enums\Platform;

/**
 * Service for detecting video platform from URLs.
 */
class PlatformDetector
{
    /**
     * Platform host suffixes used for platform detection.
     *
     * @var array<string, array<int, string>>
     */
    private const array PLATFORM_HOST_SUFFIXES = [
        Platform::YOUTUBE->value => ['youtube.com', 'youtube-nocookie.com', 'youtu.be'],
        Platform::TIKTOK->value => ['tiktok.com'],
        Platform::INSTAGRAM->value => ['instagram.com', 'instagr.am'],
        Platform::FACEBOOK->value => ['facebook.com', 'fb.watch'],
    ];

    /**
     * Platform URL patterns.
     *
     * @var array<string, array<int, string>>
     */
    private const array PLATFORM_PATTERNS = [
        Platform::YOUTUBE->value => [
            '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)*youtube\.com\//i',
            '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)*youtube-nocookie\.com\//i',
            '/(?:https?:\/\/)?(?:www\.)?youtu\.be\//i',
        ],
        Platform::TIKTOK->value => [
            '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)*tiktok\.com\//i',
        ],
        Platform::INSTAGRAM->value => [
            '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)*instagram\.com\//i',
            '/(?:https?:\/\/)?(?:www\.)?instagr\.am\//i',
        ],
        Platform::FACEBOOK->value => [
            '/(?:https?:\/\/)?(?:[a-z0-9-]+\.)*facebook\.com\//i',
            '/(?:https?:\/\/)?fb\.watch\//i',
        ],
    ];

    /**
     * Detect platform from URL.
     */
    public function detectPlatform(string $url): ?Platform
    {
        $normalizedUrl = $this->normalizeUrl($url);
        if ($normalizedUrl === '') {
            return null;
        }

        $host = $this->extractHost($normalizedUrl);

        if ($host !== null) {
            $platform = $this->detectPlatformFromHost($host);
            if ($platform !== null) {
                return $platform;
            }
        }

        foreach (self::PLATFORM_PATTERNS as $platform => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $normalizedUrl)) {
                    return Platform::tryFrom($platform);
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
        return array_map(
            fn (string $platformValue) => Platform::from($platformValue),
            array_keys(self::PLATFORM_PATTERNS)
        );
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

    private function detectPlatformFromHost(string $host): ?Platform
    {
        foreach (self::PLATFORM_HOST_SUFFIXES as $platform => $suffixes) {
            foreach ($suffixes as $suffix) {
                if ($this->hostMatchesSuffix($host, $suffix)) {
                    return Platform::tryFrom($platform);
                }
            }
        }

        return null;
    }

    private function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return '';
        }

        if (! preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $trimmed)) {
            return 'https://'.$trimmed;
        }

        return $trimmed;
    }

    private function extractHost(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return strtolower($host);
        }

        return null;
    }

    private function hostMatchesSuffix(string $host, string $suffix): bool
    {
        $host = strtolower($host);
        $suffix = strtolower($suffix);

        if ($host === $suffix) {
            return true;
        }

        return str_ends_with($host, '.'.$suffix);
    }
}
