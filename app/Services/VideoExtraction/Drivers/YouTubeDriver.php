<?php

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use Carbon\Carbon;

/**
 * YouTube video extraction driver.
 * 
 * Handles video extraction from YouTube URLs including regular videos,
 * shorts, and mobile URLs.
 */
class YouTubeDriver extends AbstractDriver
{
    /**
     * Initialize the YouTube driver.
     */
    protected function initialize(): void
    {
        $this->platform = Platform::YOUTUBE;
        
        $this->supportedQualities = [
            VideoQuality::Q144P,
            VideoQuality::Q360P,
            VideoQuality::Q720P,
            VideoQuality::Q1080P,
        ];

        $this->supportedFormats = [
            VideoFormat::MP4,
            VideoFormat::WEBM,
            VideoFormat::MP3,
        ];

        $this->urlPatterns = [
            '/^https?:\/\/(www\.)?(youtube\.com|youtu\.be|m\.youtube\.com)\/.*$/i',
            '/^https?:\/\/(www\.)?youtube\.com\/watch\?v=[\w-]+/i',
            '/^https?:\/\/youtu\.be\/[\w-]+/i',
            '/^https?:\/\/(www\.)?youtube\.com\/shorts\/[\w-]+/i',
            '/^https?:\/\/m\.youtube\.com\/watch\?v=[\w-]+/i',
        ];
    }

    /**
     * Perform the actual extraction logic for YouTube.
     */
    protected function performExtraction(string $url, array $options = []): ExtractionResult
    {
        // Reformat URL to ensure clean format for reliable extraction
        $cleanUrl = $this->reformatUrl($url);

        if (!$cleanUrl) {
            throw new \App\Services\VideoExtraction\Exceptions\ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Could not reformat URL to extractable format'
            );
        }

        // Log URL transformation if it was changed
        if ($cleanUrl !== $url) {
            \Illuminate\Support\Facades\Log::debug('YouTube URL reformatted for extraction', [
                'original_url' => $url,
                'clean_url' => $cleanUrl,
                'platform' => $this->platform->value,
            ]);
        }

        $videoId = $this->extractVideoId($cleanUrl);

        if (!$videoId) {
            throw new \App\Services\VideoExtraction\Exceptions\ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Could not extract video ID from URL'
            );
        }

        // Use yt-dlp to extract video information with clean URL
        $ytDlpData = $this->executeYtDlp($cleanUrl, $options);

        return $this->createExtractionResultFromYtDlp($ytDlpData, $options);
    }

    /**
     * Extract video ID from YouTube URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Handle different YouTube URL formats
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/v\/([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Reformat complex YouTube URLs into clean, extractable format.
     *
     * This method handles YouTube URLs with additional parameters (playlist, radio mode,
     * timestamps, etc.) and transforms them into a clean format that can be reliably
     * processed by yt-dlp and the video ID extraction logic.
     *
     * @param string $url The original YouTube URL
     * @return string|null Clean YouTube URL in format 'https://www.youtube.com/watch?v=VIDEO_ID' or null if invalid
     */
    protected function reformatUrl(string $url): ?string
    {
        // Parse the URL into components
        $parsedUrl = parse_url($url);

        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }

        // Normalize and validate YouTube domains
        $host = strtolower($parsedUrl['host']);
        $validHosts = [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'youtu.be',
        ];

        if (!in_array($host, $validHosts, true)) {
            return null;
        }

        $videoId = null;

        // Extract video ID based on URL format
        if ($host === 'youtu.be') {
            // Handle youtu.be/VIDEO_ID format
            $videoId = $this->extractVideoIdFromPath($parsedUrl['path'] ?? '');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            $path = $parsedUrl['path'] ?? '';

            if (str_starts_with($path, '/watch')) {
                // Handle /watch?v=VIDEO_ID format
                $videoId = $this->extractVideoIdFromQuery($parsedUrl['query'] ?? '');
            } elseif (str_starts_with($path, '/shorts/')) {
                // Handle /shorts/VIDEO_ID format
                $videoId = $this->extractVideoIdFromPath($path, '/shorts/');
            } elseif (str_starts_with($path, '/embed/')) {
                // Handle /embed/VIDEO_ID format
                $videoId = $this->extractVideoIdFromPath($path, '/embed/');
            } elseif (str_starts_with($path, '/v/')) {
                // Handle /v/VIDEO_ID format
                $videoId = $this->extractVideoIdFromPath($path, '/v/');
            }
        }

        // Validate video ID format
        if (!$this->isValidVideoId($videoId)) {
            return null;
        }

        // Return clean YouTube URL
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    /**
     * Extract video ID from URL path.
     *
     * @param string $path The URL path
     * @param string $prefix Optional prefix to remove from path
     * @return string|null The extracted video ID or null if not found
     */
    private function extractVideoIdFromPath(string $path, string $prefix = ''): ?string
    {
        if ($prefix && str_starts_with($path, $prefix)) {
            $path = substr($path, strlen($prefix));
        } elseif (!$prefix && str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }

        // Extract video ID (first 11 characters or until query/fragment)
        $videoId = strtok($path, '?&#');

        return $this->isValidVideoId($videoId) ? $videoId : null;
    }

    /**
     * Extract video ID from URL query string.
     *
     * @param string $query The URL query string
     * @return string|null The extracted video ID or null if not found
     */
    private function extractVideoIdFromQuery(string $query): ?string
    {
        parse_str($query, $params);

        $videoId = $params['v'] ?? null;

        return $this->isValidVideoId($videoId) ? $videoId : null;
    }

    /**
     * Validate if a string is a valid YouTube video ID.
     *
     * YouTube video IDs are exactly 11 characters long and contain
     * only alphanumeric characters, hyphens, and underscores.
     *
     * @param string|null $videoId The video ID to validate
     * @return bool True if valid, false otherwise
     */
    private function isValidVideoId(?string $videoId): bool
    {
        if (!$videoId) {
            return false;
        }

        return strlen($videoId) === 11 && preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId);
    }

    /**
     * Create extraction result from yt-dlp data.
     */
    private function createExtractionResultFromYtDlp(array $ytDlpData, array $options = []): ExtractionResult
    {
        $quality = $options['quality'] ?? VideoQuality::Q720P;
        $format = $options['format'] ?? VideoFormat::MP4;

        // Parse upload date
        $uploadDate = null;
        if (!empty($ytDlpData['upload_date'])) {
            try {
                $uploadDate = Carbon::createFromFormat('Ymd', $ytDlpData['upload_date']);
            } catch (\Exception $e) {
                // Ignore date parsing errors
            }
        }

        return ExtractionResult::create([
            'title' => $ytDlpData['title'] ?? null,
            'thumbnail_url' => $ytDlpData['thumbnail'] ?? $this->getBestThumbnail($ytDlpData),
            'duration' => $ytDlpData['duration'] ?? null,
            'video_id' => $ytDlpData['id'] ?? $this->extractVideoId($ytDlpData['webpage_url'] ?? ''),
            'file_size' => $ytDlpData['filesize'] ?? $ytDlpData['filesize_approx'] ?? null,
            'download_url' => $ytDlpData['url'] ?? null,
            'platform' => $this->platform,
            'quality' => $quality,
            'format' => $format,
            'description' => $ytDlpData['description'] ?? null,
            'author' => $ytDlpData['uploader'] ?? $ytDlpData['channel'] ?? null,
            'upload_date' => $uploadDate,
            'view_count' => $ytDlpData['view_count'] ?? null,
            'additional_metadata' => [
                'channel_id' => $ytDlpData['channel_id'] ?? null,
                'channel_url' => $ytDlpData['channel_url'] ?? null,
                'category' => $ytDlpData['categories'][0] ?? null,
                'tags' => $ytDlpData['tags'] ?? [],
                'likes' => $ytDlpData['like_count'] ?? null,
                'comments' => $ytDlpData['comment_count'] ?? null,
                'age_limit' => $ytDlpData['age_limit'] ?? null,
                'availability' => $ytDlpData['availability'] ?? null,
                'live_status' => $ytDlpData['live_status'] ?? null,
            ],
        ]);
    }

    /**
     * Get the best thumbnail from yt-dlp data.
     */
    private function getBestThumbnail(array $ytDlpData): ?string
    {
        if (!empty($ytDlpData['thumbnails'])) {
            // Sort thumbnails by preference (highest resolution first)
            $thumbnails = $ytDlpData['thumbnails'];
            usort($thumbnails, function ($a, $b) {
                $aRes = ($a['width'] ?? 0) * ($a['height'] ?? 0);
                $bRes = ($b['width'] ?? 0) * ($b['height'] ?? 0);
                return $bRes <=> $aRes;
            });

            return $thumbnails[0]['url'] ?? null;
        }

        // Fallback to standard YouTube thumbnail
        $videoId = $ytDlpData['id'] ?? $this->extractVideoId($ytDlpData['webpage_url'] ?? '');
        if ($videoId) {
            return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
        }

        return null;
    }



    /**
     * Get YouTube-specific configuration.
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'extract_comments' => false,
            'extract_subtitles' => false,
            'max_quality' => '1080p',
        ]);
    }
}
