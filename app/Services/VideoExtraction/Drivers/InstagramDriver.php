<?php

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use Carbon\Carbon;

/**
 * Instagram video extraction driver.
 * 
 * Handles video extraction from Instagram URLs including posts,
 * reels, IGTV, and stories.
 */
class InstagramDriver extends AbstractDriver
{
    /**
     * Initialize the Instagram driver.
     */
    protected function initialize(): void
    {
        $this->platform = Platform::INSTAGRAM;
        
        $this->supportedQualities = [
            VideoQuality::Q360P,
            VideoQuality::Q720P,
            VideoQuality::Q1080P,
        ];

        $this->supportedFormats = [
            VideoFormat::MP4,
            VideoFormat::MP3,
        ];

        $this->urlPatterns = [
            '/^https?:\/\/(www\.)?(instagram\.com|instagr\.am)\/.*$/i',
            '/^https?:\/\/(www\.)?instagram\.com\/p\/[\w-]+/i',
            '/^https?:\/\/(www\.)?instagram\.com\/reel\/[\w-]+/i',
            '/^https?:\/\/(www\.)?instagram\.com\/tv\/[\w-]+/i',
            '/^https?:\/\/(www\.)?instagram\.com\/stories\/[\w.-]+\/\d+/i',
        ];
    }

    /**
     * Perform the actual extraction logic for Instagram.
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

        // Use original metadata extraction approach (CDN URLs)
        $ytDlpData = $this->executeYtDlp($url, $options);

        return $this->createExtractionResultFromYtDlp($ytDlpData, $options);
    }

    /**
     * Extract video ID from Instagram URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Handle different Instagram URL formats
        $patterns = [
            '/instagram\.com\/p\/([\w-]+)/',
            '/instagram\.com\/reel\/([\w-]+)/',
            '/instagram\.com\/tv\/([\w-]+)/',
            '/instagram\.com\/stories\/[\w.-]+\/(\d+)/',
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
    protected function createExtractionResultFromYtDlp(array $ytDlpData, array $options = []): ExtractionResult
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
            'author' => $ytDlpData['uploader'] ?? null,
            'upload_date' => $uploadDate,
            'view_count' => $ytDlpData['view_count'] ?? null,
            'additional_metadata' => [
                'username' => $ytDlpData['uploader_id'] ?? null,
                'uploader_url' => $ytDlpData['uploader_url'] ?? null,
                'likes' => $ytDlpData['like_count'] ?? null,
                'comments' => $ytDlpData['comment_count'] ?? null,
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
     * Get Instagram-specific configuration.
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'handle_private_accounts' => false,
            'extract_stories' => false,
            'cookies_file' => null, // Path to cookies file if needed
        ]);
    }
}
