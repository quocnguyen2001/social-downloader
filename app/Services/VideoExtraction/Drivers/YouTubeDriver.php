<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;

/**
 * YouTube video extraction driver.
 * 
 * Handles video extraction from YouTube URLs including:
 * - youtube.com/watch?v=*
 * - youtu.be/*
 * - youtube.com/embed/*
 * - youtube.com/v/*
 * - m.youtube.com/watch?v=*
 */
class YouTubeDriver extends AbstractDriver
{
    /**
     * Get the platform this driver handles.
     */
    public function getPlatform(): Platform
    {
        return Platform::YOUTUBE;
    }

    /**
     * Get the URL patterns this driver can handle.
     */
    public function getUrlPatterns(): array
    {
        return [
            '/youtube\.com\/watch\?v=/',
            '/youtu\.be\//',
            '/youtube\.com\/embed\//',
            '/youtube\.com\/v\//',
            '/m\.youtube\.com\/watch\?v=/',
        ];
    }

    /**
     * Extract video ID from YouTube URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Pattern for youtube.com/watch?v=VIDEO_ID
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for youtu.be/VIDEO_ID
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for youtube.com/embed/VIDEO_ID
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for youtube.com/v/VIDEO_ID
        if (preg_match('/youtube\.com\/v\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        // Pattern for m.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/m\.youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
