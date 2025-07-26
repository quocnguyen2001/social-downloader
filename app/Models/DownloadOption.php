<?php

namespace App\Models;

use App\Enums\DownloadOptionStatus;
use App\Enums\DownloadOptionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DownloadOption extends Model
{
    /** @use HasFactory<\Database\Factories\DownloadOptionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'download_session_id',
        'cdn_id',
        'mime_type',
        'file_size',
        'estimated_download_time',
        'storage_disk',
        'storage_file_path',
        'quality',
        'download_cdn_url',
        'status',
    ];

    protected $casts = [
        'status' => DownloadOptionStatus::class,
        'file_size' => 'integer',
        'estimated_download_time' => 'integer',
    ];

    /**
     * Get the download session that owns this download option.
     */
    public function downloadSession(): BelongsTo
    {
        return $this->belongsTo(DownloadSession::class);
    }

    /**
     * Get the appropriate download URL based on storage location.
     *
     * If content is downloaded locally, generate URL from storage.
     * If content is on CDN, return the CDN URL.
     */
    public function getDownloadUrl(): ?string
    {
        if ($this->status === DownloadOptionStatus::DOWNLOADED && $this->storage_disk && $this->storage_file_path) {
            return Storage::disk($this->storage_disk)->url($this->storage_file_path);
        }

        if ($this->status === DownloadOptionStatus::CDN && $this->download_cdn_url) {
            return $this->download_cdn_url;
        }

        return null;
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return __('messages.labels.unknown');
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Get formatted estimated download time.
     */
    public function getFormattedEstimatedTimeAttribute(): string
    {
        if (! $this->estimated_download_time) {
            return __('messages.labels.unknown');
        }

        $seconds = $this->estimated_download_time;

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60).'m '.($seconds % 60).'s';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        return $hours.'h '.$minutes.'m '.$remainingSeconds.'s';
    }

    /**
     * Check if the download option is available for download.
     */
    public function isAvailable(): bool
    {
        return $this->getDownloadUrl() !== null;
    }

    /**
     * Check if content is stored locally.
     */
    public function isStoredLocally(): bool
    {
        return $this->status === DownloadOptionStatus::DOWNLOADED;
    }

    /**
     * Check if content is on CDN.
     */
    public function isOnCdn(): bool
    {
        return $this->status === DownloadOptionStatus::CDN;
    }

    /**
     * Mark this option as downloaded to local storage.
     */
    public function markAsDownloaded(string $storageDisk, string $storageFilePath, ?int $fileSize = null): void
    {
        $this->update([
            'status' => DownloadOptionStatus::DOWNLOADED,
            'storage_disk' => $storageDisk,
            'storage_file_path' => $storageFilePath,
            'file_size' => $fileSize ?? $this->file_size,
            'download_cdn_url' => null, // Clear CDN URL when downloaded locally
        ]);
    }

    /**
     * Mark this option as available on CDN.
     */
    public function markAsCdn(string $cdnUrl, ?int $fileSize = null): void
    {
        $this->update([
            'status' => DownloadOptionStatus::CDN,
            'download_cdn_url' => $cdnUrl,
            'file_size' => $fileSize ?? $this->file_size,
            'storage_disk' => null, // Clear local storage info when on CDN
            'storage_file_path' => null,
        ]);
    }

    /**
     * Mark this option as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => DownloadOptionStatus::PROCESSING,
        ]);
    }

    /**
     * Mark this option as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'status' => DownloadOptionStatus::FAILED,
        ]);
    }

    /**
     * Get the download option type based on quality field.
     */
    public function getType(): DownloadOptionType
    {
        return match ($this->quality) {
            'audio' => DownloadOptionType::ONLY_AUDIO,
            '144', '360', '720', '1080' => DownloadOptionType::ONLY_VIDEO,
            default => DownloadOptionType::FULL,
        };
    }

    /**
     * Check if this is an audio-only option.
     */
    public function isAudioOnly(): bool
    {
        return $this->quality === 'audio';
    }

    /**
     * Check if this is a video-only option.
     */
    public function isVideoOnly(): bool
    {
        return in_array($this->quality, ['144', '360', '720', '1080']);
    }

    /**
     * Check if this is a full video option (with audio).
     */
    public function isFull(): bool
    {
        return ! $this->isAudioOnly() && ! $this->isVideoOnly();
    }
}
