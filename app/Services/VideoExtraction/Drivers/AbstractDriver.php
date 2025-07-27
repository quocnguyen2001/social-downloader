<?php

declare(strict_types=1);

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat as VideoFormatEnum;
use App\Enums\VideoQuality;
use App\Services\ThumbnailService;
use App\Services\VideoExtraction\Contracts\DriverInterface;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use App\Services\VideoExtraction\Exceptions\ExtractionFailedException;
use App\Services\VideoExtraction\Exceptions\InvalidUrlException;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Support\Facades\Log;

/**
 * Abstract base driver for video extraction.
 *
 * This class provides common functionality for all platform drivers
 * using yt-dlp as the underlying extraction tool.
 */
abstract class AbstractDriver implements DriverInterface
{
    protected YtDlpService $ytDlpService;

    protected ThumbnailService $thumbnailService;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->ytDlpService = app(YtDlpService::class);
        $this->thumbnailService = app(ThumbnailService::class);
    }

    /**
     * Extract video metadata from the given URL.
     */
    public function extractMetadata(string $url, array $options = []): ExtractionResult
    {
        if (! $this->validateUrl($url)) {
            throw new InvalidUrlException("Invalid URL for {$this->getPlatform()->value}: {$url}");
        }

        try {
            Log::info('Extracting metadata with yt-dlp', [
                'url' => $url,
                'platform' => $this->getPlatform()->value,
                'options' => $options,
            ]);

            // Get video metadata
            $metadata = $this->ytDlpService->getVideoMetadata($url);

            // Get available formats
            $formats = $this->ytDlpService->getAvailableFormats($url);

            if (empty($formats)) {
                throw new ExtractionFailedException('No formats available for this video');
            }

            // Handle thumbnail download and storage
            $thumbnailDisk = null;
            $thumbnailPath = null;
            $thumbnailUrl = $metadata['thumbnail_url'] ?? $metadata['thumbnail'] ?? null;

            if ($thumbnailUrl) {
                $videoId = $this->extractVideoId($url);
                $platform = $this->getPlatform()->value;

                $thumbnailInfo = $this->thumbnailService->downloadAndStore($thumbnailUrl, $videoId, $platform);
                if ($thumbnailInfo) {
                    $thumbnailDisk = $thumbnailInfo['disk'];
                    $thumbnailPath = $thumbnailInfo['path'];

                    Log::info('Thumbnail downloaded and stored', [
                        'video_id' => $videoId,
                        'platform' => $platform,
                        'disk' => $thumbnailDisk,
                        'path' => $thumbnailPath,
                    ]);
                }
            }

            $result = new ExtractionResult(
                title: $metadata['title'] ?? null,
                thumbnailUrl: $thumbnailUrl, // Keep for backward compatibility
                thumbnailDisk: $thumbnailDisk,
                thumbnailPath: $thumbnailPath,
                duration: $metadata['duration'] ?? null,
                videoId: $this->extractVideoId($url),
                platform: $this->getPlatform(),
                quality: $options['quality'] ?? null,
                format: $options['format'] ?? null,
                description: $metadata['description'] ?? null,
                author: $metadata['uploader'] ?? null,
                uploadDate: $metadata['upload_date'] ?? null,
                viewCount: $metadata['view_count'] ?? null,
                additionalMetadata: [
                    'formats' => $formats,
                    'original_url' => $url,
                    'extractor' => $metadata['extractor'] ?? null,
                    'full_metadata' => $metadata,
                ]
            );

            Log::info('Metadata extracted successfully', [
                'url' => $url,
                'platform' => $this->getPlatform()->value,
                'title' => $result->getTitle(),
                'formats_count' => count($formats),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to extract metadata', [
                'url' => $url,
                'platform' => $this->getPlatform()->value,
                'error' => $e->getMessage(),
            ]);

            throw new ExtractionFailedException(
                "Failed to extract metadata for {$this->getPlatform()->value}: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Get the download URL for the video with specified quality and format.
     */
    public function getDownloadUrl(string $url, VideoQuality $quality, VideoFormatEnum $format): string
    {
        if (! $this->validateUrl($url)) {
            throw new InvalidUrlException("Invalid URL for {$this->getPlatform()->value}: {$url}");
        }

        try {
            $formats = $this->ytDlpService->getAvailableFormats($url);

            // Find the best matching format
            $selectedFormat = $this->selectBestFormat($formats, $quality, $format);

            if (! $selectedFormat) {
                throw new ExtractionFailedException('No suitable format found for the requested quality and format');
            }

            return $selectedFormat->url;

        } catch (\Exception $e) {
            throw new ExtractionFailedException(
                "Failed to get download URL for {$this->getPlatform()->value}: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Check if this driver supports the given URL.
     */
    public function supports(string $url): bool
    {
        $patterns = $this->getUrlPatterns();

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate if the given URL is valid for this platform.
     */
    public function validateUrl(string $url): bool
    {
        return $this->supports($url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get the supported video qualities for this platform.
     */
    public function getSupportedQualities(): array
    {
        $platformConfig = config("video-extraction.drivers.{$this->getPlatform()->value}");
        $qualityStrings = $platformConfig['supported_qualities'] ?? [];

        return array_map(fn ($quality) => VideoQuality::tryFrom($quality), $qualityStrings);
    }

    /**
     * Get the supported video formats for this platform.
     */
    public function getSupportedFormats(): array
    {
        $platformConfig = config("video-extraction.drivers.{$this->getPlatform()->value}");
        $formatStrings = $platformConfig['supported_formats'] ?? [];

        return array_map(fn ($format) => VideoFormatEnum::tryFrom($format), $formatStrings);
    }

    /**
     * Extract video ID from URL (platform-specific implementation).
     */
    abstract protected function extractVideoId(string $url): ?string;

    /**
     * Select the best format based on quality and format preferences.
     */
    protected function selectBestFormat(array $formats, VideoQuality $quality, VideoFormatEnum $format): ?object
    {
        // Simple implementation - return the first format that matches
        // This can be enhanced with more sophisticated matching logic
        foreach ($formats as $availableFormat) {
            if ($this->formatMatches($availableFormat, $quality, $format)) {
                return $availableFormat;
            }
        }

        // Fallback to first available format
        return $formats[0] ?? null;
    }

    /**
     * Check if a format matches the requested quality and format.
     */
    protected function formatMatches(object $availableFormat, VideoQuality $quality, VideoFormatEnum $format): bool
    {
        // Basic matching logic - can be enhanced
        return true; // For now, accept any format
    }
}
