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
        $videoId = $this->extractVideoId($url);

        if (!$videoId) {
            throw new \App\Services\VideoExtraction\Exceptions\ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Could not extract video ID from URL'
            );
        }

        // Use yt-dlp to extract video information
        $ytDlpData = $this->executeYtDlp($url, $options);

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
