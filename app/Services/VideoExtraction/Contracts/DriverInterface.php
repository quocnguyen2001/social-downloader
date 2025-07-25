<?php

namespace App\Services\VideoExtraction\Contracts;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\DTOs\ExtractionResult;

/**
 * Interface for video extraction drivers.
 *
 * Each platform driver must implement this interface to provide
 * consistent video extraction capabilities across different platforms.
 */
interface DriverInterface
{
    /**
     * Extract video metadata from the given URL.
     *
     * @param  string  $url  The video URL to extract metadata from
     * @param  array  $options  Additional options for extraction
     * @return ExtractionResult The extracted video metadata
     *
     * @throws \App\Services\VideoExtraction\Exceptions\ExtractionFailedException
     * @throws \App\Services\VideoExtraction\Exceptions\InvalidUrlException
     */
    public function extractMetadata(string $url, array $options = []): ExtractionResult;

    /**
     * Get the download URL for the video with specified quality and format.
     *
     * @param  string  $url  The original video URL
     * @param  VideoQuality  $quality  The desired video quality
     * @param  VideoFormat  $format  The desired video format
     * @return string The download URL
     *
     * @throws \App\Services\VideoExtraction\Exceptions\ExtractionFailedException
     * @throws \App\Services\VideoExtraction\Exceptions\InvalidUrlException
     */
    public function getDownloadUrl(string $url, VideoQuality $quality, VideoFormat $format): string;

    /**
     * Validate if the given URL is valid for this platform.
     *
     * @param  string  $url  The URL to validate
     * @return bool True if the URL is valid for this platform
     */
    public function validateUrl(string $url): bool;

    /**
     * Check if this driver supports the given URL.
     *
     * @param  string  $url  The URL to check
     * @return bool True if this driver can handle the URL
     */
    public function supports(string $url): bool;

    /**
     * Get the platform this driver handles.
     *
     * @return Platform The platform enum value
     */
    public function getPlatform(): Platform;

    /**
     * Get the supported video qualities for this platform.
     *
     * @return array<VideoQuality> Array of supported video qualities
     */
    public function getSupportedQualities(): array;

    /**
     * Get the supported video formats for this platform.
     *
     * @return array<VideoFormat> Array of supported video formats
     */
    public function getSupportedFormats(): array;

    /**
     * Get the URL patterns this driver can handle.
     *
     * @return array<string> Array of regex patterns
     */
    public function getUrlPatterns(): array;
}
