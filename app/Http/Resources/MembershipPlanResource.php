<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Membership Plan Resource for API responses.
 *
 * Transforms membership plan data for consistent API output.
 */
class MembershipPlanResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => $this->price,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'billing_cycle_label' => $this->billing_cycle_label,
            'formatted_price' => $this->formatted_price,
            'daily_request_limit' => $this->daily_request_limit,
            'total_request_download' => $this->total_request_download,
            'allowed_platforms' => $this->allowed_platforms,
            'allowed_qualities' => $this->allowed_qualities,
            'allowed_formats' => $this->allowed_formats,
            'priority_processing' => $this->priority_processing,
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
            'has_unlimited_daily_requests' => $this->hasUnlimitedRequests('daily'),
            'has_unlimited_total_requests' => $this->hasUnlimitedRequests('total'),
        ];
    }
}
