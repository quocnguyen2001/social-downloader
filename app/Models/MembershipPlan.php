<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Membership Plan Model
 *
 * Defines different membership tiers with request limits and features.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property string $currency
 * @property string $billing_cycle
 * @property int $daily_request_limit
 * @property int $total_request_download
 * @property array|null $allowed_platforms
 * @property array|null $allowed_qualities
 * @property array|null $allowed_formats
 * @property bool $priority_processing
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MembershipPlan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_cycle',
        'daily_request_limit',
        'total_request_download',
        'allowed_platforms',
        'allowed_qualities',
        'allowed_formats',
        'priority_processing',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'daily_request_limit' => 'integer',
        'total_request_download' => 'integer',
        'allowed_platforms' => 'array',
        'allowed_qualities' => 'array',
        'allowed_formats' => 'array',
        'priority_processing' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the users that belong to this membership plan.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Scope to get only active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get featured plans.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    /**
     * Check if the plan allows unlimited requests for a specific period.
     */
    public function hasUnlimitedRequests(string $period = 'daily'): bool
    {
        return match ($period) {
            'daily' => $this->daily_request_limit === 0,
            'total' => $this->total_request_download === 0,
            default => false,
        };
    }

    /**
     * Get the request limit for a specific period.
     */
    public function getRequestLimit(string $period = 'daily'): int
    {
        return match ($period) {
            'daily' => $this->daily_request_limit,
            'total' => $this->total_request_download,
            default => 0,
        };
    }

    /**
     * Check if the plan has expired.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the plan is active and not expired.
     */
    public function isActiveAndValid(): bool
    {
        return $this->is_active && ! $this->hasExpired();
    }

    /**
     * Check if a platform is allowed for this plan.
     */
    public function allowsPlatform(string $platform): bool
    {
        if (empty($this->allowed_platforms)) {
            return true; // No restrictions
        }

        return in_array($platform, $this->allowed_platforms);
    }

    /**
     * Check if a quality is allowed for this plan.
     */
    public function allowsQuality(string $quality): bool
    {
        if (empty($this->allowed_qualities)) {
            return true; // No restrictions
        }

        return in_array($quality, $this->allowed_qualities);
    }

    /**
     * Check if a format is allowed for this plan.
     */
    public function allowsFormat(string $format): bool
    {
        if (empty($this->allowed_formats)) {
            return true; // No restrictions
        }

        return in_array($format, $this->allowed_formats);
    }

    /**
     * Get the formatted price with currency.
     */
    public function getFormattedPriceAttribute(): string
    {
        if ($this->price == 0) {
            return 'Free';
        }

        return $this->currency.' '.number_format($this->price, 2);
    }

    /**
     * Get the billing cycle label.
     */
    public function getBillingCycleLabelAttribute(): string
    {
        return match ($this->billing_cycle) {
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'lifetime' => 'Lifetime',
            default => ucfirst($this->billing_cycle),
        };
    }

    /**
     * Get all available platforms.
     */
    public static function getAvailablePlatforms(): array
    {
        return [
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
        ];
    }

    /**
     * Get all available qualities.
     */
    public static function getAvailableQualities(): array
    {
        return [
            '144p' => '144p',
            '360p' => '360p',
            '720p' => '720p',
            '1080p' => '1080p',
        ];
    }

    /**
     * Get all available formats.
     */
    public static function getAvailableFormats(): array
    {
        return [
            'mp4' => 'MP4',
            'webm' => 'WebM',
            'mp3' => 'MP3',
        ];
    }

    /**
     * Get all available billing cycles.
     */
    public static function getAvailableBillingCycles(): array
    {
        return [
            'monthly' => 'Monthly',
            'yearly' => 'Yearly',
            'lifetime' => 'Lifetime',
        ];
    }
}
