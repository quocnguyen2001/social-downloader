<?php

namespace App\Models;

use App\Enums\ApiKeyStatus;
use App\Enums\Platform;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Import Order model for relationship

class ApiKey extends Model
{
    /** @use HasFactory<\Database\Factories\ApiKeyFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'id',
        'name',
        'key_hash',
        'key_prefix',
        'status',
        'price_per_request',
        'daily_limit',
        'monthly_limit',
        'daily_usage',
        'monthly_usage',
        'total_usage',
        'last_reset_daily',
        'last_reset_monthly',
        'contact_email',
        'billing_email',
        'company_name',
        'webhook_url',
        'allowed_platforms',
        'allowed_qualities',
        'allowed_formats',
    ];

    protected $casts = [
        'status' => ApiKeyStatus::class,
        'allowed_platforms' => 'array',
        'allowed_qualities' => 'array',
        'allowed_formats' => 'array',
        'last_reset_daily' => 'date',
        'last_reset_monthly' => 'date',
        'price_per_request' => 'decimal:4',
    ];

    /**
     * Get all API requests for this API key.
     */
    public function apiRequests()
    {
        return $this->hasMany(ApiRequest::class);
    }

    /**
     * Get all orders for this API key.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get all download sessions for this API key.
     */
    public function downloadSessions()
    {
        return $this->hasMany(DownloadSession::class);
    }

    /**
     * Check if the API key can make a request based on limits and status.
     */
    public function canMakeRequest(): bool
    {
        if ($this->status !== ApiKeyStatus::ACTIVE) {
            return false;
        }

        $this->resetUsageIfNeeded();

        return $this->daily_usage < $this->daily_limit &&
               $this->monthly_usage < $this->monthly_limit;
    }

    /**
     * Increment usage counters for this API key.
     */
    public function incrementUsage(): void
    {
        $this->resetUsageIfNeeded();

        $this->increment('daily_usage');
        $this->increment('monthly_usage');
        $this->increment('total_usage');
    }

    /**
     * Reset usage counters if needed based on dates.
     */
    public function resetUsageIfNeeded(): void
    {
        $today = now()->toDateString();
        $currentMonth = now()->startOfMonth()->toDateString();

        // Reset daily usage if needed
        if ($this->last_reset_daily->toDateString() !== $today) {
            $this->update([
                'daily_usage' => 0,
                'last_reset_daily' => $today,
            ]);
        }

        // Reset monthly usage if needed
        if ($this->last_reset_monthly->toDateString() !== $currentMonth) {
            $this->update([
                'monthly_usage' => 0,
                'last_reset_monthly' => $currentMonth,
            ]);
        }
    }

    /**
     * Generate a new API key.
     */
    public static function generateKey(): string
    {
        return 'vd_live_'.\Illuminate\Support\Str::random(32);
    }

    /**
     * Get the usage percentage for daily limit.
     */
    public function getDailyUsagePercentageAttribute(): float
    {
        if ($this->daily_limit === 0) {
            return 0;
        }

        return round(($this->daily_usage / $this->daily_limit) * 100, 2);
    }

    /**
     * Get the usage percentage for monthly limit.
     */
    public function getMonthlyUsagePercentageAttribute(): float
    {
        if ($this->monthly_limit === 0) {
            return 0;
        }

        return round(($this->monthly_usage / $this->monthly_limit) * 100, 2);
    }

    /**
     * Get formatted price per request.
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price_per_request, 4).' VND';
    }

    /**
     * Check if a platform is allowed for this API key.
     */
    public function isPlatformAllowed(string $platform): bool
    {
        return in_array($platform, $this->allowed_platforms ?? []);
    }

    /**
     * Check if a quality is allowed for this API key.
     */
    public function isQualityAllowed(string $quality): bool
    {
        return in_array($quality, $this->allowed_qualities ?? []);
    }

    /**
     * Check if a format is allowed for this API key.
     */
    public function isFormatAllowed(string $format): bool
    {
        return in_array($format, $this->allowed_formats ?? []);
    }

    /**
     * Scope to filter active API keys.
     */
    public function scopeActive($query)
    {
        return $query->where('status', ApiKeyStatus::ACTIVE);
    }

    /**
     * Scope to filter inactive API keys.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', ApiKeyStatus::INACTIVE);
    }

    /**
     * Scope to filter suspended API keys.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', ApiKeyStatus::SUSPENDED);
    }
}

// Import for Order relationship
