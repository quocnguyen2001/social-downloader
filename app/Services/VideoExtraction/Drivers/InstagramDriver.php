<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat as VideoFormatEnum;
use App\Enums\VideoQuality;
use Illuminate\Support\Facades\Log;

/**
 * Instagram video extraction driver.
 *
 * Handles video extraction from Instagram URLs including:
 * - instagram.com/p/*
 * - instagram.com/reel/*
 * - instagram.com/tv/*
 * - instagr.am/p/*
 *
 * Instagram uses DASH formats with complex IDs and requires special handling
 * for format selection based on resolution, bitrate, and codec information.
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

    /**
     * Select the best format for Instagram based on quality and format preferences.
     * Instagram uses DASH formats that require special handling.
     */
    protected function selectBestFormat(array $formats, VideoQuality $quality, VideoFormatEnum $format): ?object
    {
        Log::info('Selecting best Instagram format', [
            'total_formats' => count($formats),
            'requested_quality' => $quality->value,
            'requested_format' => $format->value,
        ]);

        // Filter formats suitable for download
        $suitableFormats = array_filter($formats, fn ($f) => $f->isSuitableForDownload());

        if (empty($suitableFormats)) {
            Log::warning('No suitable formats found for Instagram video');

            return $formats[0] ?? null;
        }

        // For audio requests, find the best audio format
        if ($format === VideoFormatEnum::MP3) {
            Log::info('Instagram: Selecting audio format', [
                'suitable_formats_count' => count($suitableFormats),
            ]);

            return $this->selectBestAudioFormat($suitableFormats);
        }

        // For video requests, find the best video format
        Log::info('Instagram: Selecting video format', [
            'suitable_formats_count' => count($suitableFormats),
            'requested_quality' => $quality->value,
        ]);

        return $this->selectBestVideoFormat($suitableFormats, $quality);
    }

    /**
     * Select the best audio format for Instagram.
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
     * Select the best video format for Instagram based on quality preference.
     */
    private function selectBestVideoFormat(array $formats, VideoQuality $quality): ?object
    {
        // Filter video formats (including video-only and combined)
        $videoFormats = array_filter($formats, fn ($f) => ! $f->isAudioOnly);

        if (empty($videoFormats)) {
            Log::warning('No video formats found');

            return null;
        }

        // Score each format based on Instagram-specific criteria
        $scoredFormats = [];
        foreach ($videoFormats as $format) {
            $score = $this->getInstagramQualityScore($format, $quality);
            $scoredFormats[] = ['format' => $format, 'score' => $score];

            Log::debug('Format scored', [
                'format_id' => $format->formatId,
                'resolution' => $format->resolution,
                'score' => $score,
            ]);
        }

        // Sort by score (highest first)
        usort($scoredFormats, fn ($a, $b) => $b['score'] <=> $a['score']);

        $bestFormat = $scoredFormats[0]['format'];

        Log::info('Selected best video format', [
            'format_id' => $bestFormat->formatId,
            'resolution' => $bestFormat->resolution,
            'score' => $scoredFormats[0]['score'],
        ]);

        return $bestFormat;
    }

    /**
     * Calculate quality score for Instagram format based on multiple criteria.
     */
    private function getInstagramQualityScore(object $format, VideoQuality $quality): int
    {
        $score = 0;

        // Resolution scoring (Instagram uses portrait orientation)
        $resolutionScore = $this->getResolutionScore($format->resolution);
        $score += $resolutionScore * 100; // Weight resolution heavily

        // Bitrate scoring
        $bitrate = $format->tbr ?? $format->vbr ?? 0;
        $score += min($bitrate / 10, 50); // Cap bitrate contribution

        // File size scoring (larger usually means better quality)
        if ($format->filesize) {
            $fileSizeMB = $format->filesize / (1024 * 1024);
            $score += min($fileSizeMB, 20); // Cap file size contribution
        }

        // Codec scoring (prefer newer codecs)
        $codecScore = $this->getCodecScore($format->vcodec);
        $score += $codecScore;

        // Quality preference matching
        $qualityMatchScore = $this->getQualityMatchScore($format, $quality);
        $score += $qualityMatchScore * 50; // Weight quality matching

        return (int) $score;
    }

    /**
     * Get resolution score for Instagram formats.
     */
    private function getResolutionScore(string $resolution): int
    {
        // Handle Instagram's portrait resolutions
        if (preg_match('/(\d+)x(\d+)/', $resolution, $matches)) {
            $height = (int) $matches[2];

            return match (true) {
                $height >= 1920 => 10, // 1080x1920 or higher
                $height >= 1280 => 8,  // 720x1280
                $height >= 720 => 6,   // 480x720 or similar
                $height >= 480 => 4,   // 360x480 or similar
                default => 2,
            };
        }

        // Handle direct quality labels
        if (preg_match('/(\d+)p/', $resolution, $matches)) {
            $quality = (int) $matches[1];

            return match (true) {
                $quality >= 1080 => 10,
                $quality >= 720 => 8,
                $quality >= 480 => 6,
                $quality >= 360 => 4,
                default => 2,
            };
        }

        return 1; // Default low score for unknown resolutions
    }

    /**
     * Get codec score for Instagram formats.
     */
    private function getCodecScore(?string $codec): int
    {
        if (! $codec) {
            return 0;
        }

        return match (true) {
            str_contains($codec, 'vp09.00.40') => 10, // VP9 Profile 2
            str_contains($codec, 'vp09.00.31') => 8,  // VP9 Profile 0
            str_contains($codec, 'vp09') => 6,        // Other VP9
            str_contains($codec, 'h264') => 4,        // H.264
            str_contains($codec, 'avc1') => 4,        // AVC
            default => 2,
        };
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
     * Extract numeric quality from format.
     * For Instagram video formats without clear quality indicators, default to 1080p.
     */
    private function extractQualityFromFormat(object $format): int
    {
        // Try resolution first
        if (preg_match('/(\d+)x(\d+)/', $format->resolution, $matches)) {
            return (int) $matches[2]; // Return height
        }

        // Try quality label
        if ($format->qualityLabel && preg_match('/(\d+)p/', $format->qualityLabel, $matches)) {
            return (int) $matches[1];
        }

        // Try extracting from resolution string
        if (preg_match('/(\d+)p/', $format->resolution, $matches)) {
            return (int) $matches[1];
        }

        // For Instagram video formats without clear quality indicators,
        // assume highest quality (1080p) as requested
        if (! $format->isAudioOnly && $format->extension === 'mp4') {
            return 1080;
        }

        return 360; // Default fallback for other cases
    }

    /**
     * Override extractMetadata to add Instagram-specific error handling and logging.
     */
    public function extractMetadata(string $url, array $options = []): \App\Services\VideoExtraction\DTOs\ExtractionResult
    {
        Log::info('Starting Instagram metadata extraction', [
            'url' => $url,
            'options' => $options,
        ]);

        try {
            $result = parent::extractMetadata($url, $options);

            // Log Instagram-specific extraction results
            Log::info('Instagram metadata extraction completed', [
                'url' => $url,
                'title' => $result->getTitle(),
                'thumbnail_found' => ! empty($result->getThumbnail()),
                'duration' => $result->getDuration(),
                'formats_count' => count($result->getAdditionalMetadata()['formats'] ?? []),
            ]);

            // Validate Instagram-specific requirements
            if (empty($result->getThumbnail())) {
                Log::warning('Instagram thumbnail extraction failed', [
                    'url' => $url,
                    'metadata_keys' => array_keys($result->getAdditionalMetadata()['full_metadata'] ?? []),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Instagram metadata extraction failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
