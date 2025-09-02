<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Models\MembershipPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\MembershipPlanResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MembershipPlanController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request): JsonResponse
    {
        try {
            $activeOnly = $request->boolean('active_only', true);
            $featuredOnly = $request->boolean('featured_only', false);

            $query = MembershipPlan::query();

            if ($activeOnly) {
                $query->active();
            }

            if ($featuredOnly) {
                $query->featured();
            }

            $plans = $query->ordered()->get();

            $plansData = MembershipPlanResource::collection($plans);

            Log::info('Membership plans retrieved via API', [
                'count' => $plans->count(),
                'active_only' => $activeOnly,
                'featured_only' => $featuredOnly,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiSuccessResponse(
                $plansData->toArray($request),
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

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $plan = MembershipPlan::findOrFail($id);

            // Check if the plan is active (optional - you can remove this if you want to show inactive plans too)
            if (!$plan->is_active) {
                return $this->apiErrorResponse(
                    __('Membership plan not found or not available.'),
                    null,
                    404
                );
            }

            $planData = new MembershipPlanResource($plan);

            Log::info('Membership plan retrieved via API', [
                'plan_id' => $id,
                'plan_name' => $plan->name,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiSuccessResponse(
                $planData->toArray($request),
                __('Membership plan retrieved successfully.')
            );

        } catch (ModelNotFoundException $e) {
            Log::warning('Membership plan not found via API', [
                'plan_id' => $id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiErrorResponse(
                __('Membership plan not found.'),
                null,
                404
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve membership plan via API', [
                'plan_id' => $id,
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to retrieve membership plan.'),
                null,
                500
            );
        }
    }
}
