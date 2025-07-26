<?php

namespace App\Services\VideoExtraction\DTOs;

use App\Enums\DownloadOptionStatus;
use App\Enums\DownloadOptionType;

/**
 * Data Transfer Object for video format information from yt-dlp.
 */
class VideoFormat
{
    public function __construct(
        public readonly string $formatId,
        public readonly string $extension,
        public readonly string $resolution,
        public readonly ?int $fps = null,
        public readonly ?int $filesize = null,
        public readonly ?int $tbr = null,
        public readonly ?string $protocol = null,
        public readonly ?string $vcodec = null,
        public readonly ?int $vbr = null,
        public readonly ?string $acodec = null,
        public readonly ?int $abr = null,
        public readonly ?string $formatNote = null,
        public readonly bool $isVideoOnly = false,
        public readonly bool $isAudioOnly = false,
        public readonly ?string $qualityLabel = null,
        public readonly ?string $language = null
    ) {}

    /**
     * Convert to array for database storage.
     * Maps yt-dlp data to existing database columns.
     */
    public function toArray(): array
    {
        // Standardize quality value according to new requirements
        $quality = $this->getStandardizedQuality();

        // Create a descriptive mime type based on extension and codecs
        $mimeType = $this->getMimeType();

        return [
            'cdn_id' => $this->formatId,
            'quality' => $quality,
            'mime_type' => $mimeType,
            'file_size' => $this->filesize, // This might be null if not available from yt-dlp
            'status' => DownloadOptionStatus::CDN->value, // Store as string value for database
        ];
    }

    /**
     * Get standardized quality value according to new requirements.
     */
    private function getStandardizedQuality(): string
    {
        // Handle audio-only formats
        if ($this->isAudioOnly || $this->resolution === 'audio only') {
            return 'audio';
        }

        // Extract height from resolution like "1920x1080" and map to standard qualities
        if (preg_match('/(\d+)x(\d+)/', $this->resolution, $matches)) {
            $height = (int) $matches[2];

            return $this->mapInstagramHeightToStandardQuality($height);
        }

        // Handle direct quality labels like "720p"
        if (preg_match('/(\d+)p/', $this->resolution, $matches)) {
            return $matches[1];
        }

        // Use qualityLabel if available and extract number
        if ($this->qualityLabel && preg_match('/(\d+)p/', $this->qualityLabel, $matches)) {
            return $matches[1];
        }

        // Extract any number from resolution as fallback
        if (preg_match('/(\d+)/', $this->resolution, $matches)) {
            return $matches[1];
        }

        // For Instagram video formats without clear quality indicators,
        // default to highest quality (1080) as requested
        if (! $this->isAudioOnly && $this->extension === 'mp4') {
            return '1080';
        }

        // Final fallback to original resolution
        return $this->resolution;
    }

    /**
     * Generate a descriptive MIME type.
     */
    private function getMimeType(): string
    {
        // Map common extensions to MIME types
        $mimeTypes = [
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'flv' => 'video/x-flv',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
        ];

        $baseMimeType = $mimeTypes[$this->extension] ?? 'application/octet-stream';

        // Add codec information if available
        $codecInfo = [];
        if ($this->vcodec && $this->vcodec !== 'none') {
            $codecInfo[] = $this->vcodec;
        }
        if ($this->acodec && $this->acodec !== 'none') {
            $codecInfo[] = $this->acodec;
        }

        if (! empty($codecInfo)) {
            $baseMimeType .= '; codecs="'.implode(', ', $codecInfo).'"';
        }

        return $baseMimeType;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSize(): string
    {
        if (! $this->filesize) {
            return __('messages.labels.unknown');
        }

        $bytes = $this->filesize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get format type description.
     */
    public function getTypeDescription(): string
    {
        if ($this->isAudioOnly) {
            return __('video_extraction.format_types.audio_only');
        }

        if ($this->isVideoOnly) {
            return __('video_extraction.format_types.video_only');
        }

        return __('video_extraction.format_types.combined');
    }

    /**
     * Get codec information.
     */
    public function getCodecInfo(): string
    {
        $codecs = [];

        if ($this->vcodec && $this->vcodec !== 'none') {
            $codecs[] = $this->vcodec;
        }

        if ($this->acodec && $this->acodec !== 'none') {
            $codecs[] = $this->acodec;
        }

        return implode(', ', $codecs) ?: __('messages.labels.unknown');
    }

    /**
     * Check if this format is suitable for download.
     */
    public function isSuitableForDownload(): bool
    {
        // Skip formats that are clearly not downloadable
        if (str_contains($this->formatNote ?? '', 'DRM') ||
            str_contains($this->formatNote ?? '', 'Premium')) {
            return false;
        }

        // Prefer formats with known protocols
        if ($this->protocol && in_array($this->protocol, ['https', 'http'])) {
            return true;
        }

        // Allow dash and m3u8 formats as they're common
        if ($this->protocol && in_array($this->protocol, ['dash', 'm3u8'])) {
            return true;
        }

        return true; // Default to allowing the format
    }

    /**
     * Get the appropriate DownloadOptionType for this format.
     */
    public function getDownloadOptionType(): DownloadOptionType
    {
        if ($this->isAudioOnly) {
            return DownloadOptionType::ONLY_AUDIO;
        }

        if ($this->isVideoOnly) {
            return DownloadOptionType::ONLY_VIDEO;
        }

        // If it has both video and audio, it's a full format
        return DownloadOptionType::FULL;
    }

    /**
     * Map Instagram's portrait resolution heights to standard quality values.
     * Instagram uses portrait orientation (e.g., 720x1280, 1080x1920).
     */
    private function mapInstagramHeightToStandardQuality(int $height): string
    {
        return match (true) {
            $height >= 1920 => '1080', // 1080x1920 → 1080p
            $height >= 1280 => '720',  // 720x1280 → 720p
            $height >= 640 => '360',   // 360x640 → 360p
            $height >= 480 => '360',   // Fallback for lower resolutions
            default => '144',          // Very low quality fallback
        };
    }
}
