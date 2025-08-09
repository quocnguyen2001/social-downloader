<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Membership Plan Controller for API endpoints.
 *
 * Handles public access to membership plan information.
 */
class MembershipPlanController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all active membership plans.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get query parameters for filtering
            $activeOnly = $request->boolean('active_only', true);
            $featuredOnly = $request->boolean('featured_only', false);

            // Build query
            $query = MembershipPlan::query();

            // Apply filters
            if ($activeOnly) {
                $query->active();
            }

            if ($featuredOnly) {
                $query->featured();
            }

            // Get plans ordered by sort order and price
            $plans = $query->ordered()->get();

            // Transform plans data for API response
            $plansData = $plans->map(function (MembershipPlan $plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'billing_cycle' => $plan->billing_cycle,
                    'billing_cycle_label' => $plan->billing_cycle_label,
                    'formatted_price' => $plan->formatted_price,
                    'daily_request_limit' => $plan->daily_request_limit,
                    'total_request_download' => $plan->total_request_download,
                    'allowed_platforms' => $plan->allowed_platforms,
                    'allowed_qualities' => $plan->allowed_qualities,
                    'allowed_formats' => $plan->allowed_formats,
                    'priority_processing' => $plan->priority_processing,
                    'is_active' => $plan->is_active,
                    'is_featured' => $plan->is_featured,
                    'sort_order' => $plan->sort_order,
                    'has_unlimited_daily_requests' => $plan->hasUnlimitedRequests('daily'),
                    'has_unlimited_total_requests' => $plan->hasUnlimitedRequests('total'),
                ];
            });

            Log::info('Membership plans retrieved via API', [
                'count' => $plansData->count(),
                'active_only' => $activeOnly,
                'featured_only' => $featuredOnly,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiSuccessResponse(
                $plansData->toArray(),
                __('Membership plans retrieved successfully.')
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve membership plans via API', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to retrieve membership plans.'),
                null,
                500
            );
        }
    }
}
