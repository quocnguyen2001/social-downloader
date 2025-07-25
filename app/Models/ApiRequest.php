<?php

namespace App\Models;

use App\Enums\HttpMethod;
use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiRequest extends Model
{
    /** @use HasFactory<\Database\Factories\ApiRequestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'api_key_id',
        'user_id',
        'endpoint',
        'method',
        'ip_address',
        'user_agent',
        'original_url',
        'platform',
        'video_title',
        'requested_quality',
        'requested_format',
        'status_code',
        'response_time',
        'file_size',
        'download_url',
        'cost',
        'billed',
    ];

    protected $casts = [
        'platform' => Platform::class,
        'requested_quality' => VideoQuality::class,
        'requested_format' => VideoFormat::class,
        'method' => HttpMethod::class,
        'cost' => 'decimal:4',
        'billed' => 'boolean',
    ];

    /**
     * Get the API key that owns this request.
     */
    public function apiKey()
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Get the user that made this request.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter requests by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to filter requests by platform.
     */
    public function scopePlatform($query, $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope to filter requests by status code.
     */
    public function scopeStatusCode($query, $statusCode)
    {
        return $query->where('status_code', $statusCode);
    }

    /**
     * Scope to filter successful requests (status code 200).
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status_code', 200);
    }

    /**
     * Scope to filter failed requests (status code != 200).
     */
    public function scopeFailed($query)
    {
        return $query->where('status_code', '!=', 200);
    }

    /**
     * Scope to filter billed requests.
     */
    public function scopeBilled($query)
    {
        return $query->where('billed', true);
    }

    /**
     * Scope to filter unbilled requests.
     */
    public function scopeUnbilled($query)
    {
        return $query->where('billed', false);
    }

    /**
     * Scope to filter requests by API key.
     */
    public function scopeForApiKey($query, $apiKeyId)
    {
        return $query->where('api_key_id', $apiKeyId);
    }

    /**
     * Scope to filter requests from today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    /**
     * Scope to filter requests from this month.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year);
    }

    /**
     * Get formatted cost with currency.
     */
    public function getFormattedCostAttribute(): string
    {
        return number_format($this->cost, 4).' VND';
    }

    /**
     * Get formatted response time.
     */
    public function getFormattedResponseTimeAttribute(): string
    {
        if (! $this->response_time) {
            return 'N/A';
        }

        return $this->response_time.'ms';
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Check if the request was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status_code === 200;
    }

    /**
     * Check if the request failed.
     */
    public function isFailed(): bool
    {
        return $this->status_code !== 200;
    }

    /**
     * Get the status badge color for UI.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match (true) {
            $this->status_code === 200 => 'success',
            $this->status_code >= 400 && $this->status_code < 500 => 'warning',
            $this->status_code >= 500 => 'danger',
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
}
