<?php

namespace App\Services\VideoExtraction\DTOs;

use App\Enums\DownloadOptionStatus;

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
     *
     * @return array
     */
    public function toArray(): array
    {
        // Determine the type based on video/audio content
        $type = $this->getDownloadOptionType();

        // Use quality label or resolution for quality field
        $quality = $this->qualityLabel ?: $this->resolution;

        // Create a descriptive mime type based on extension and codecs
        $mimeType = $this->getMimeType();

        return [
            'cdn_id' => $this->formatId,
            'quality' => $quality,
            'type' => $type->value, // Store as string value for database
            'mime_type' => $mimeType,
            'file_size' => $this->filesize,
            'status' => DownloadOptionStatus::CDN->value, // Store as string value for database
        ];
    }

    /**
     * Get the appropriate DownloadOptionType enum value.
     */
    private function getDownloadOptionType(): \App\Enums\DownloadOptionType
    {
        if ($this->isAudioOnly) {
            return \App\Enums\DownloadOptionType::ONLY_AUDIO;
        }

        if ($this->isVideoOnly) {
            return \App\Enums\DownloadOptionType::ONLY_VIDEO;
        }

        return \App\Enums\DownloadOptionType::FULL;
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

        if (!empty($codecInfo)) {
            $baseMimeType .= '; codecs="' . implode(', ', $codecInfo) . '"';
        }

        return $baseMimeType;
    }

    /**
     * Get formatted file size.
     *
     * @return string
     */
    public function getFormattedFileSize(): string
    {
        if (!$this->filesize) {
            return __('messages.labels.unknown');
        }

        $bytes = $this->filesize;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get format type description.
     *
     * @return string
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
     *
     * @return string
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
     *
     * @return bool
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
}
