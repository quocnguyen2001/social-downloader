<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'membership_plan_id',
        'membership_started_at',
        'membership_expires_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'membership_started_at' => 'datetime',
            'membership_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the membership plan that the user belongs to.
     */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /**
     * Get the download sessions for the user.
     */
    public function downloadSessions(): HasMany
    {
        return $this->hasMany(DownloadSession::class);
    }

    /**
     * Get the API requests for the user.
     */
    public function apiRequests(): HasMany
    {
        return $this->hasMany(ApiRequest::class);
    }

    /**
     * Get the transactions for the user.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Check if the user has a specific membership plan.
     */
    public function hasMembershipPlan(string $planSlug): bool
    {
        return $this->membershipPlan && $this->membershipPlan->slug === $planSlug;
    }

    /**
     * Check if the user can make requests based on their plan limits.
     */
    public function canMakeRequest(string $period = 'daily'): bool
    {
        if (! $this->membershipPlan) {
            return false; // No plan assigned
        }

        $limit = $this->membershipPlan->getRequestLimit($period);

        if ($limit === 0) {
            return true; // Unlimited
        }

        // Count user's requests for the period
        $requestCount = $this->getRequestCount($period);

        return $requestCount < $limit;
    }

    /**
     * Get the user's request count for a specific period.
     */
    public function getRequestCount(string $period = 'daily'): int
    {
        $query = $this->downloadSessions();

        $startDate = match ($period) {
            'daily' => now()->startOfDay(),
            'monthly' => now()->startOfMonth(),
            default => now()->startOfDay(),
        };

        return $query->where('created_at', '>=', $startDate)->count();
    }

    /**
     * Get remaining requests for a specific period.
     */
    public function getRemainingRequests(string $period = 'daily'): int
    {
        if (! $this->membershipPlan) {
            return 0;
        }

        $limit = $this->membershipPlan->getRequestLimit($period);

        if ($limit === 0) {
            return PHP_INT_MAX; // Unlimited
        }

        $used = $this->getRequestCount($period);

        return max(0, $limit - $used);
    }

    /**
     * Check if the user's membership is active.
     */
    public function hasMembershipActive(): bool
    {
        if (! $this->membershipPlan) {
            return false;
        }

        // If no expiration date, consider it active
        if (! $this->membership_expires_at) {
            return true;
        }

        return $this->membership_expires_at->isFuture();
    }

    /**
     * Check if the user's membership is expired.
     */
    public function hasMembershipExpired(): bool
    {
        return ! $this->hasMembershipActive();
    }

    /**
     * Get days until membership expires.
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (! $this->membership_expires_at) {
            return null; // No expiration
        }

        return max(0, now()->diffInDays($this->membership_expires_at, false));
    }

    /**
     * Assign a membership plan to the user.
     */
    public function assignMembershipPlan(MembershipPlan $plan, ?\Carbon\Carbon $expiresAt = null): void
    {
        $this->update([
            'membership_plan_id' => $plan->id,
            'membership_started_at' => now(),
            'membership_expires_at' => $expiresAt,
        ]);
    }

    /**
     * Remove membership plan from the user.
     */
    public function removeMembershipPlan(): void
    {
        $this->update([
            'membership_plan_id' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
    }

    /**
     * Update the last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->update(['last_login_at' => now()]);
    }

    /**
     * Get the count of active API tokens.
     */
    public function getActiveTokensCountAttribute(): int
    {
        return $this->tokens()->count();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
