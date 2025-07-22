<?php

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use Carbon\Carbon;

/**
 * Facebook video extraction driver.
 * 
 * Handles video extraction from Facebook URLs including regular posts,
 * watch videos, and mobile URLs.
 */
class FacebookDriver extends AbstractDriver
{
    /**
     * Initialize the Facebook driver.
     */
    protected function initialize(): void
    {
        $this->platform = Platform::FACEBOOK;
        
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
            '/^https?:\/\/(www\.)?(facebook\.com|fb\.watch|m\.facebook\.com)\/.*$/i',
            '/^https?:\/\/(www\.)?facebook\.com\/watch\/\?v=\d+/i',
            '/^https?:\/\/(www\.)?facebook\.com\/[\w.-]+\/videos\/\d+/i',
            '/^https?:\/\/fb\.watch\/[\w-]+/i',
            '/^https?:\/\/m\.facebook\.com\/watch\/\?v=\d+/i',
        ];
    }

    /**
     * Perform the actual extraction logic for Facebook.
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
     * Extract video ID from Facebook URL.
     */
    protected function extractVideoId(string $url): ?string
    {
        // Handle different Facebook URL formats
        $patterns = [
            '/facebook\.com\/watch\/\?v=(\d+)/',
            '/facebook\.com\/[\w.-]+\/videos\/(\d+)/',
            '/fb\.watch\/([\w-]+)/',
            '/m\.facebook\.com\/watch\/\?v=(\d+)/',
            '/facebook\.com\/video\.php\?v=(\d+)/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        // For fb.watch URLs, we might need to follow redirects
        if (preg_match('/fb\.watch/', $url)) {
            return 'fb_' . substr(md5($url), 0, 10);
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
                'uploader_id' => $ytDlpData['uploader_id'] ?? null,
                'uploader_url' => $ytDlpData['uploader_url'] ?? null,
                'likes' => $ytDlpData['like_count'] ?? null,
                'comments' => $ytDlpData['comment_count'] ?? null,
                'shares' => $ytDlpData['repost_count'] ?? null,
                'age_limit' => $ytDlpData['age_limit'] ?? null,
                'availability' => $ytDlpData['availability'] ?? null,
            ],
        ]);
    }

    /**
     * Get Facebook-specific configuration.
     */
    protected function getDefaultConfig(): array
    {
        return array_merge(parent::getDefaultConfig(), [
            'handle_private_videos' => false,
            'cookies_file' => null, // Path to cookies file if needed for private content
        ]);
    }
}
