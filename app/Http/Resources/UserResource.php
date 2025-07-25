<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User Resource for API responses.
 *
 * Transforms user data for consistent API output, including membership plan information.
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
            'membership_plan' => $this->whenLoaded('membershipPlan', function () {
                return new MembershipPlanResource($this->membershipPlan);
            }),
            'membership_started_at' => $this->membership_started_at?->toISOString(),
            'membership_expires_at' => $this->membership_expires_at?->toISOString(),
            'membership_active' => $this->hasMembershipActive(),
            'membership_expires_in_days' => $this->getDaysUntilExpiration(),
            'active_tokens_count' => $this->when(
                $this->relationLoaded('tokens'),
                fn () => $this->tokens->count(),
                fn () => $this->tokens()->count()
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
