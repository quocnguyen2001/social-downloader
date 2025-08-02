<?php

namespace App\Models;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DownloadSession extends Model
{
    /** @use HasFactory<\Database\Factories\DownloadSessionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'api_key_id',
        'user_id',
        'original_url',
        'platform',
        'video_id',
        'title',
        'thumbnail_path',
        'thumbnail_disk',
        'duration',
        'status',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'platform' => Platform::class,
        'status' => DownloadSessionStatus::class,
        'expires_at' => 'datetime',
    ];

    /**
     * Get the API key that owns this download session.
     */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Get the user that owns this download session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the download options for this session.
     */
    public function downloadOptions(): HasMany
    {
        return $this->hasMany(DownloadOption::class);
    }

    /**
     * Get the thumbnail URL from stored path and disk.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path || ! $this->thumbnail_disk) {
            return null;
        }

        try {
            return Storage::disk($this->thumbnail_disk)->url($this->thumbnail_path);
        } catch (\Exception $e) {
            // Log the error and return null if storage disk is not configured properly
            \Log::warning('Failed to generate thumbnail URL', [
                'download_session_id' => $this->id,
                'thumbnail_path' => $this->thumbnail_path,
                'thumbnail_disk' => $this->thumbnail_disk,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Mark the session as ready for download.
     */
    public function markAsReadyForDownload(): void
    {
        $this->update([
            'status' => DownloadSessionStatus::READY_FOR_DOWNLOAD,
            'error_message' => null,
            'expires_at' => now()->addHours(24), // Session expires in 24 hours
        ]);
    }

    /**
     * Mark the session as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => DownloadSessionStatus::PENDING, // Reset to pending for retry
            'error_message' => $errorMessage,
            'expires_at' => now()->addHours(24), // Keep record for 24 hours
        ]);
    }

    /**
     * Mark the session as fetching metadata.
     */
    public function markAsFetchingMetadata(): void
    {
        $this->update([
            'status' => DownloadSessionStatus::FETCHING_METADATA,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the session as metadata fetched.
     */
    public function markAsMetadataFetched(): void
    {
        $this->update([
            'status' => DownloadSessionStatus::METADATA_FETCHED,
            'expires_at' => Carbon::now()->addHours(24),
            'error_message' => null,
        ]);
    }

    /**
     * Mark the session as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'expires_at' => now(),
        ]);
    }

    /**
     * Check if the session is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if the session is ready for download.
     */
    public function isReadyForDownload(): bool
    {
        return $this->status === DownloadSessionStatus::READY_FOR_DOWNLOAD && ! $this->isExpired();
    }

    /**
     * Check if the session is completed (ready for download).
     */
    public function isCompleted(): bool
    {
        return $this->status === DownloadSessionStatus::READY_FOR_DOWNLOAD;
    }

    /**
     * Check if the session is failed (has error message).
     */
    public function isFailed(): bool
    {
        return ! empty($this->error_message);
    }

    /**
     * Check if the session is pending.
     */
    public function isPending(): bool
    {
        return $this->status === DownloadSessionStatus::PENDING;
    }

    /**
     * Check if the session is processing (fetching metadata).
     */
    public function isProcessing(): bool
    {
        return $this->status === DownloadSessionStatus::FETCHING_METADATA;
    }

    /**
     * Check if the session is fetching metadata.
     */
    public function isFetchingMetadata(): bool
    {
        return $this->status === DownloadSessionStatus::FETCHING_METADATA;
    }

    /**
     * Check if the session has metadata fetched.
     */
    public function isMetadataFetched(): bool
    {
        return $this->status === DownloadSessionStatus::METADATA_FETCHED;
    }

    /**
     * Scope to filter sessions by status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter pending sessions.
     */
    public function scopePending($query)
    {
        return $query->where('status', DownloadSessionStatus::PENDING);
    }

    /**
     * Scope to filter processing sessions (fetching metadata).
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', DownloadSessionStatus::FETCHING_METADATA);
    }

    /**
     * Scope to filter sessions that are fetching metadata.
     */
    public function scopeFetchingMetadata($query)
    {
        return $query->where('status', DownloadSessionStatus::FETCHING_METADATA);
    }

    /**
     * Scope to filter sessions with metadata fetched.
     */
    public function scopeMetadataFetched($query)
    {
        return $query->where('status', DownloadSessionStatus::METADATA_FETCHED);
    }

    /**
     * Scope to filter completed sessions (ready for download).
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD);
    }

    /**
     * Scope to filter sessions ready for download.
     */
    public function scopeReadyForDownload($query)
    {
        return $query->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD);
    }

    /**
     * Scope to filter failed sessions (with error messages).
     */
    public function scopeFailed($query)
    {
        return $query->whereNotNull('error_message');
    }

    /**
     * Scope to filter expired sessions.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
    }

    /**
     * Scope to filter sessions by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter sessions by API key.
     */
    public function scopeForApiKey($query, $apiKeyId)
    {
        return $query->where('api_key_id', $apiKeyId);
    }

    /**
     * Scope to filter active sessions (not expired).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            DownloadSessionStatus::PENDING,
            DownloadSessionStatus::FETCHING_METADATA,
            DownloadSessionStatus::METADATA_FETCHED,
            DownloadSessionStatus::READY_FOR_DOWNLOAD,
        ])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (! $this->duration) {
            return 'N/A';
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Get the status badge color for UI.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            DownloadSessionStatus::PENDING => 'warning',
            DownloadSessionStatus::FETCHING_METADATA => 'info',
            DownloadSessionStatus::METADATA_FETCHED => 'primary',
            DownloadSessionStatus::READY_FOR_DOWNLOAD => 'success',
            default => 'secondary',
        };
    }

    /**
     * Get the platform icon for UI.
     */
    public function getPlatformIconAttribute(): string
    {
        return match ($this->platform) {
            'youtube' => 'heroicon-o-play',
            'tiktok' => 'heroicon-o-musical-note',
            'instagram' => 'heroicon-o-camera',
            'facebook' => 'heroicon-o-users',
            default => 'heroicon-o-globe-alt',
        };
    }

    /**
     * Get time remaining until expiration.
     */
    public function getTimeUntilExpirationAttribute(): ?string
    {
        if (! $this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }

    /**
     * Check if the thumbnail is available and valid.
     */
    public function isThumbnailValid(): bool
    {
        if (! $this->thumbnail_path || ! $this->thumbnail_disk) {
            return false;
        }

        try {
            // Check if the file exists on the storage disk
            return Storage::disk($this->thumbnail_disk)->exists($this->thumbnail_path);
        } catch (\Exception $e) {
            // If there's an error accessing the storage disk, consider thumbnail invalid
            \Log::warning('Failed to check thumbnail validity', [
                'download_session_id' => $this->id,
                'thumbnail_path' => $this->thumbnail_path,
                'thumbnail_disk' => $this->thumbnail_disk,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if Instagram thumbnail URL is valid and not expired.
     *
     * @deprecated This method is kept for backward compatibility but may not be needed
     * with the new S3 storage system. Consider removing in future versions.
     */
    private function isInstagramThumbnailValid(): bool
    {
        // This method is deprecated as we now store thumbnails on S3
        // instead of relying on Instagram's URLs
        return $this->isThumbnailValid();
    }
}
