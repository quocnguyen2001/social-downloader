<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;

/**
 * Instagram video extraction driver.
 * 
 * Handles video extraction from Instagram URLs including:
 * - instagram.com/p/*
 * - instagram.com/reel/*
 * - instagram.com/tv/*
 * - instagr.am/p/*
 */
class InstagramDriver extends AbstractDriver
{
    /**
     * Get the platform this driver handles.
     */
    public function getPlatform(): Platform
    {
        return Platform::INSTAGRAM;
    }

    /**
     * Get the URL patterns this driver can handle.
     */
    public function getUrlPatterns(): array
    {
        return [
            '/instagram\.com\/p\//',
            '/instagram\.com\/reel\//',
            '/instagram\.com\/tv\//',
            '/instagr\.am\/p\//',
        ];
    }

    /**
     * Extract video ID from Instagram URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Pattern for instagram.com/p/POST_ID
        if (preg_match('/instagram\.com\/p\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for instagram.com/reel/REEL_ID
        if (preg_match('/instagram\.com\/reel\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for instagram.com/tv/TV_ID
        if (preg_match('/instagram\.com\/tv\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for instagr.am/p/POST_ID
        if (preg_match('/instagr\.am\/p\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
