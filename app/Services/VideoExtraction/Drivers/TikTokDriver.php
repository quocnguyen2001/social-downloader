<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;

/**
 * TikTok video extraction driver.
 *
 * Handles video extraction from TikTok URLs including:
 * - tiktok.com/@user/video/*
 * - vm.tiktok.com/*
 * - vt.tiktok.com/*
 * - m.tiktok.com/*
 */
class TikTokDriver extends AbstractDriver
{
    /**
     * Get the platform this driver handles.
     */
    public function getPlatform(): Platform
    {
        return Platform::TIKTOK;
    }

    /**
     * Get the URL patterns this driver can handle.
     */
    public function getUrlPatterns(): array
    {
        return [
            '/tiktok\.com\//',
            '/vm\.tiktok\.com\//',
            '/vt\.tiktok\.com\//',
            '/m\.tiktok\.com\//',
        ];
    }

    /**
     * Extract video ID from TikTok URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Pattern for tiktok.com/@user/video/VIDEO_ID
        if (preg_match('/tiktok\.com\/@[^\/]+\/video\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for vm.tiktok.com/VIDEO_ID
        if (preg_match('/vm\.tiktok\.com\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for vt.tiktok.com/VIDEO_ID
        if (preg_match('/vt\.tiktok\.com\/([a-zA-Z0-9]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for m.tiktok.com/v/VIDEO_ID
        if (preg_match('/m\.tiktok\.com\/v\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
