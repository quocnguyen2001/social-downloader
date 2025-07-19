<?php

namespace App\Models;

use App\Enums\Platform;
use App\Enums\VideoQuality;
use App\Enums\VideoFormat;
use App\Enums\DownloadSessionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DownloadSession extends Model
{
    /** @use HasFactory<\Database\Factories\DownloadSessionFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'api_key_id',
        'original_url',
        'platform',
        'video_id',
        'title',
        'thumbnail_url',
        'duration',
        'quality',
        'format',
        'file_size',
        'download_url',
        'status',
        'error_message',
        'expires_at',
    ];

    protected $casts = [
        'platform' => Platform::class,
        'quality' => VideoQuality::class,
        'format' => VideoFormat::class,
        'status' => DownloadSessionStatus::class,
        'expires_at' => 'datetime',
    ];

    /**
     * Get the API key that owns this download session.
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Mark the session as completed.
     */
    public function markAsCompleted(string $downloadUrl, int $fileSize): void
    {
        $this->update([
            'status' => DownloadSessionStatus::COMPLETED,
            'download_url' => $downloadUrl,
            'file_size' => $fileSize,
            'error_message' => null,
            'expires_at' => now()->addHours(24), // Download link expires in 24 hours
        ]);
    }

    /**
     * Mark the session as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => DownloadSessionStatus::FAILED,
            'error_message' => $errorMessage,
            'download_url' => null,
            'file_size' => null,
            'expires_at' => now()->addHours(24), // Keep record for 24 hours
        ]);
    }

    /**
     * Mark the session as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => DownloadSessionStatus::PROCESSING,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the session as expired.
     */
    public function markAsExpired(): void
    {
        $this->update([
            'status' => DownloadSessionStatus::EXPIRED,
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
     * Check if the session is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === DownloadSessionStatus::COMPLETED;
    }

    /**
     * Check if the session is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === DownloadSessionStatus::FAILED;
    }

    /**
     * Check if the session is pending.
     */
    public function isPending(): bool
    {
        return $this->status === DownloadSessionStatus::PENDING;
    }

    /**
     * Check if the session is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === DownloadSessionStatus::PROCESSING;
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
     * Scope to filter processing sessions.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', DownloadSessionStatus::PROCESSING);
    }

    /**
     * Scope to filter completed sessions.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', DownloadSessionStatus::COMPLETED);
    }

    /**
     * Scope to filter failed sessions.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', DownloadSessionStatus::FAILED);
    }

    /**
     * Scope to filter expired sessions.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', DownloadSessionStatus::EXPIRED)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('expires_at')
                              ->where('expires_at', '<', now());
                    });
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
     * Scope to filter active sessions (not expired or failed).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
                        DownloadSessionStatus::PENDING,
                        DownloadSessionStatus::PROCESSING,
                        DownloadSessionStatus::COMPLETED
                    ])
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                    });
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get formatted duration.
     */
    public function getFormattedDurationAttribute(): string
    {
        if (!$this->duration) {
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
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'failed' => 'danger',
            'expired' => 'secondary',
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
        if (!$this->expires_at) {
            return null;
        }

        if ($this->expires_at->isPast()) {
            return 'Expired';
        }

        return $this->expires_at->diffForHumans();
    }
}
