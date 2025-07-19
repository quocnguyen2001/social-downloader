<?php

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use Carbon\Carbon;

/**
 * TikTok video extraction driver.
 * 
 * Handles video extraction from TikTok URLs including regular videos,
 * mobile URLs, and short URLs.
 */
class TikTokDriver extends AbstractDriver
{
    /**
     * Initialize the TikTok driver.
     */
    protected function initialize(): void
    {
        $this->platform = Platform::TIKTOK;
        
        $this->supportedQualities = [
            VideoQuality::Q360P,
            VideoQuality::Q720P,
        ];

        $this->supportedFormats = [
            VideoFormat::MP4,
            VideoFormat::MP3,
        ];

        $this->urlPatterns = [
            '/^https?:\/\/(www\.)?(tiktok\.com|vm\.tiktok\.com|m\.tiktok\.com)\/.*$/i',
            '/^https?:\/\/(www\.)?tiktok\.com\/@[\w.-]+\/video\/\d+/i',
            '/^https?:\/\/vm\.tiktok\.com\/[\w]+/i',
            '/^https?:\/\/m\.tiktok\.com\/v\/\d+/i',
        ];
    }

    /**
     * Perform the actual extraction logic for TikTok.
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
     * Extract video ID from TikTok URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Handle different TikTok URL formats
        $patterns = [
            '/tiktok\.com\/@[\w.-]+\/video\/(\d+)/',
            '/vm\.tiktok\.com\/([\w]+)/',
            '/m\.tiktok\.com\/v\/(\d+)/',
            '/tiktok\.com\/t\/([\w]+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        // For short URLs, we might need to follow redirects
        // This is a simplified approach for mock data
        if (preg_match('/vm\.tiktok\.com|tiktok\.com\/t\//', $url)) {
            return 'mock_' . substr(md5($url), 0, 10);
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
            'title' => $ytDlpData['title'] ?? $ytDlpData['description'] ?? null,
            'thumbnail_url' => $ytDlpData['thumbnail'] ?? null,
            'duration' => $ytDlpData['duration'] ?? null,
            'video_id' => $ytDlpData['id'] ?? $this->extractVideoId($ytDlpData['webpage_url'] ?? ''),
            'file_size' => $ytDlpData['filesize'] ?? $ytDlpData['filesize_approx'] ?? null,
            'download_url' => $ytDlpData['url'] ?? null,
            'platform' => $this->platform,
            'quality' => $quality,
            'format' => $format,
            'description' => $ytDlpData['description'] ?? null,
            'author' => $ytDlpData['uploader'] ?? $ytDlpData['creator'] ?? null,
            'upload_date' => $uploadDate,
            'view_count' => $ytDlpData['view_count'] ?? null,
            'additional_metadata' => [
                'username' => $ytDlpData['uploader_id'] ?? null,
                'uploader_url' => $ytDlpData['uploader_url'] ?? null,
                'likes' => $ytDlpData['like_count'] ?? null,
                'comments' => $ytDlpData['comment_count'] ?? null,
                'shares' => $ytDlpData['repost_count'] ?? null,
                'hashtags' => $this->extractHashtags($ytDlpData['description'] ?? ''),
                'age_limit' => $ytDlpData['age_limit'] ?? null,
            ],
        ]);
    }

    /**
     * Extract hashtags from description.
     */
    private function extractHashtags(string $description): array
    {
        preg_match_all('/#[\w]+/', $description, $matches);
        return $matches[0] ?? [];
    }

    /**
     * Get TikTok-specific configuration.
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'follow_redirects' => true,
            'max_redirect_depth' => 5,
            'extract_music_info' => true,
        ]);
    }
}
