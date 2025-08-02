<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat as VideoFormatEnum;
use App\Enums\VideoQuality;
use Illuminate\Support\Facades\Log;

/**
 * YouTube video extraction driver.
 *
 * Handles video extraction from YouTube URLs including:
 * - youtube.com/watch?v=*
 * - youtu.be/*
 * - youtube.com/embed/*
 * - youtube.com/v/*
 * - m.youtube.com/watch?v=*
 *
 * This driver prioritizes MP4 format over WebM for all YouTube downloads.
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

    /**
     * Select the best format for YouTube based on quality and format preferences.
     * Prioritizes MP4 format over WebM for all video downloads.
     */
    protected function selectBestFormat(array $formats, VideoQuality $quality, VideoFormatEnum $format): ?object
    {
        Log::info('Selecting best YouTube format', [
            'total_formats' => count($formats),
            'requested_quality' => $quality->value,
            'requested_format' => $format->value,
        ]);

        // Filter formats suitable for download
        $suitableFormats = array_filter($formats, fn ($f) => $f->isSuitableForDownload());

        if (empty($suitableFormats)) {
            Log::warning('No suitable formats found for YouTube video');

            return $formats[0] ?? null;
        }

        // For audio requests, find the best audio format
        if ($format === VideoFormatEnum::MP3) {
            Log::info('YouTube: Selecting audio format');

            return $this->selectBestAudioFormat($suitableFormats);
        }

        // For video requests, prioritize MP4 format
        Log::info('YouTube: Selecting video format with MP4 preference', [
            'suitable_formats_count' => count($suitableFormats),
            'requested_quality' => $quality->value,
        ]);

        return $this->selectBestVideoFormat($suitableFormats, $quality, $format);
    }

    /**
     * Select the best audio format for YouTube.
     */
    private function selectBestAudioFormat(array $formats): ?object
    {
        $audioFormats = array_filter($formats, fn ($f) => $f->isAudioOnly);

        if (empty($audioFormats)) {
            Log::info('No audio-only formats found, looking for combined formats');
            $audioFormats = array_filter($formats, fn ($f) => ! $f->isVideoOnly);
        }

        if (empty($audioFormats)) {
            return null;
        }

        // Sort by bitrate (higher is better for audio)
        usort($audioFormats, function ($a, $b) {
            $aBitrate = $a->abr ?? $a->tbr ?? 0;
            $bBitrate = $b->abr ?? $b->tbr ?? 0;

            return $bBitrate <=> $aBitrate;
        });

        Log::info('Selected best audio format', [
            'format_id' => $audioFormats[0]->formatId,
            'bitrate' => $audioFormats[0]->abr ?? $audioFormats[0]->tbr,
        ]);

        return $audioFormats[0];
    }

    /**
     * Select the best video format for YouTube with MP4 preference.
     */
    private function selectBestVideoFormat(array $formats, VideoQuality $quality, VideoFormatEnum $format): ?object
    {
        // Filter video formats (including video-only and combined)
        $videoFormats = array_filter($formats, fn ($f) => ! $f->isAudioOnly);

        if (empty($videoFormats)) {
            Log::warning('No video formats found');

            return null;
        }

        // Score each format based on YouTube-specific criteria with MP4 preference
        $scoredFormats = [];
        foreach ($videoFormats as $videoFormat) {
            $score = $this->getYouTubeQualityScore($videoFormat, $quality, $format);
            $scoredFormats[] = ['format' => $videoFormat, 'score' => $score];

            Log::debug('Format scored', [
                'format_id' => $videoFormat->formatId,
                'extension' => $videoFormat->extension,
                'resolution' => $videoFormat->resolution,
                'score' => $score,
            ]);
        }

        // Sort by score (highest first)
        usort($scoredFormats, fn ($a, $b) => $b['score'] <=> $a['score']);

        $bestFormat = $scoredFormats[0]['format'];

        Log::info('Selected best video format', [
            'format_id' => $bestFormat->formatId,
            'extension' => $bestFormat->extension,
            'resolution' => $bestFormat->resolution,
            'score' => $scoredFormats[0]['score'],
        ]);

        return $bestFormat;
    }

    /**
     * Calculate quality score for YouTube format with MP4 preference.
     */
    private function getYouTubeQualityScore(object $format, VideoQuality $quality, VideoFormatEnum $requestedFormat): int
    {
        $score = 0;

        // Heavy preference for MP4 format when requesting video
        if ($requestedFormat === VideoFormatEnum::MP4 || $requestedFormat === VideoFormatEnum::WEBM) {
            if ($format->extension === 'mp4') {
                $score += 1000; // Very high bonus for MP4
                Log::debug('MP4 format bonus applied', ['format_id' => $format->formatId]);
            } elseif ($format->extension === 'webm') {
                $score += 100; // Lower score for WebM
                Log::debug('WebM format penalty applied', ['format_id' => $format->formatId]);
            }
        }

        // Quality matching score
        $qualityScore = $this->getQualityMatchScore($format, $quality);
        $score += $qualityScore * 10;

        // Bitrate scoring
        $bitrate = $format->tbr ?? $format->vbr ?? 0;
        $score += min($bitrate / 100, 50); // Cap bitrate contribution

        // File size scoring (larger usually means better quality)
        if ($format->filesize) {
            $fileSizeMB = $format->filesize / (1024 * 1024);
            $score += min($fileSizeMB / 10, 20); // Cap file size contribution
        }

        // Prefer combined formats over video-only when possible
        if (! $format->isVideoOnly && ! $format->isAudioOnly) {
            $score += 50; // Bonus for combined formats
        }

        return (int) $score;
    }

    /**
     * Get quality match score based on user preference.
     */
    private function getQualityMatchScore(object $format, VideoQuality $quality): int
    {
        $formatQuality = $this->extractQualityFromFormat($format);

        return match ($quality) {
            VideoQuality::Q1080P => $formatQuality >= 1080 ? 10 : max(0, 10 - abs($formatQuality - 1080) / 100),
            VideoQuality::Q720P => $formatQuality >= 720 ? 10 : max(0, 10 - abs($formatQuality - 720) / 100),
            VideoQuality::Q360P => $formatQuality >= 360 ? 10 : max(0, 10 - abs($formatQuality - 360) / 100),
            VideoQuality::Q144P => $formatQuality >= 144 ? 10 : max(0, 10 - abs($formatQuality - 144) / 100),
            default => 5, // Neutral score for unknown quality
        };
    }

    /**
     * Extract numeric quality from format object.
     */
    private function extractQualityFromFormat(object $format): int
    {
        // Try to extract from resolution string
        if (preg_match('/(\d+)x(\d+)/', $format->resolution, $matches)) {
            return (int) $matches[2]; // Height
        }

        // Try to extract from quality label
        if ($format->qualityLabel && preg_match('/(\d+)p/', $format->qualityLabel, $matches)) {
            return (int) $matches[1];
        }

        // Try to extract from resolution directly
        if (preg_match('/(\d+)p/', $format->resolution, $matches)) {
            return (int) $matches[1];
        }

        return 0; // Unknown quality
    }

    /**
     * Check if a format matches the requested quality and format with MP4 preference.
     */
    protected function formatMatches(object $availableFormat, VideoQuality $quality, VideoFormatEnum $format): bool
    {
        // For video formats, strongly prefer MP4
        if ($format === VideoFormatEnum::MP4 || $format === VideoFormatEnum::WEBM) {
            // Prefer MP4 over WebM
            if ($availableFormat->extension === 'mp4') {
                return true; // Always accept MP4 formats
            }

            // Only accept WebM if specifically requested or as fallback
            if ($availableFormat->extension === 'webm' && $format === VideoFormatEnum::WEBM) {
                return true;
            }
        }

        // For audio formats
        if ($format === VideoFormatEnum::MP3 && $availableFormat->isAudioOnly) {
            return true;
        }

        return false;
    }
}
