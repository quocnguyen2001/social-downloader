<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\DownloadSessionResource;
use App\Http\Traits\ApiResponseTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\DownloadSession;
use App\Enums\DownloadSessionStatus;

/**
 * User Controller for API endpoints.
 *
 * Handles user profile management operations including
 * retrieving and updating user information.
 */
class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get current authenticated user information.
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Load membership plan relationship
            $user->load('membershipPlan');

            $now = now();

            // Calculate download statistics
            $totalDownloads = DownloadSession::query()
                ->where('user_id', $user->id)
                ->count();

            $currentMonthDownloads = DownloadSession::query()
                ->where('user_id', $user->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            $successfulDownloads = DownloadSession::query()
                ->where('user_id', $user->id)
                ->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD)
                ->count();

            $successRate = $totalDownloads > 0 ? round(($successfulDownloads / $totalDownloads) * 100, 2) : 0;

            $dailyRequestLimit = $user->membershipPlan?->daily_request_limit;
            $totalRequestLimit = $user->membershipPlan?->total_request_download;

            $dailySuccessfulRequests = DownloadSession::query()
                ->where('user_id', $user->id)
                ->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD)
                ->whereDate('created_at', $now->toDateString())
                ->count();

            $membershipStartDate = $user->membership_started_at
                ? $user->membership_started_at->copy()->startOfDay()
                : null;

            $totalSuccessfulRequests = DownloadSession::query()
                ->where('user_id', $user->id)
                ->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD)
                ->when($membershipStartDate, function ($query) use ($membershipStartDate) {
                    $query->where('created_at', '>=', $membershipStartDate);
                })
                ->count();

            $dailyRequestsRemaining = $dailyRequestLimit && $dailyRequestLimit > 0
                ? max(0, $dailyRequestLimit - $dailySuccessfulRequests)
                : null;

            $totalRequestsRemaining = $totalRequestLimit && $totalRequestLimit > 0 && $membershipStartDate
                ? max(0, $totalRequestLimit - $totalSuccessfulRequests)
                : null;

            $downloadStatistics = [
                'total_downloads' => $totalDownloads,
                'current_month_downloads' => $currentMonthDownloads,
                'success_rate' => $successRate,
                'daily_requests_remaining' => $dailyRequestsRemaining,
                'total_requests_remaining' => $totalRequestsRemaining,
            ];

            $userData = new UserResource($user);

            $userData->additional([
                'download_statistics' => $downloadStatistics,
            ]);

            Log::info('User profile retrieved', [
                'user_id' => $user->id,
                'email' => $user->email,
                'total_downloads' => $totalDownloads,
                'current_month_downloads' => $currentMonthDownloads,
                'success_rate' => $successRate,
                'daily_requests_remaining' => $dailyRequestsRemaining,
                'total_requests_remaining' => $totalRequestsRemaining,
                'ip_address' => $request->ip(),
            ]);

            $result = [
                ...$userData->toArray($request),
                'download_statistics' => $downloadStatistics,
            ];

            return $this->apiSuccessResponse(
                $result,
                __('User profile retrieved successfully.')
            );

        } catch (Exception $e) {
            Log::error('Failed to retrieve user profile', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to retrieve user profile.'),
                null,
                500
            );
        }
    }

    /**
     * Update current authenticated user information.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validatedData = $request->validated();

            // Track what fields are being updated
            $updatedFields = [];

            // Update name if provided
            if (isset($validatedData['name'])) {
                $user->name = $validatedData['name'];
                $updatedFields[] = 'name';
            }

            // Update email if provided
            if (isset($validatedData['email']) && $validatedData['email'] !== $user->email) {
                $user->email = $validatedData['email'];
                $user->email_verified_at = null; // Reset email verification
                $updatedFields[] = 'email';
            }

            // Update password if provided
            if (isset($validatedData['password'])) {
                $user->password = Hash::make($validatedData['password']);
                $updatedFields[] = 'password';

                // Revoke all existing tokens for security (except current one)
                $currentToken = $user->currentAccessToken();
                $user->tokens()->where('id', '!=', $currentToken?->id)->delete();
            }

            // Save changes
            $user->save();

            // Load membership plan relationship for response
            $user->load('membershipPlan');

            // Transform user data using resource
            $userData = new UserResource($user);

            Log::info('User profile updated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'updated_fields' => $updatedFields,
                'ip_address' => $request->ip(),
            ]);

            return $this->apiSuccessResponse(
                $userData,
                __('User profile updated successfully.')
            );

        } catch (Exception $e) {
            Log::error('Failed to update user profile', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to update user profile.'),
                null,
                500
            );
        }
    }

    /**
     * Get recent download sessions for the authenticated user.
     */
    public function recentDownloads(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = $request->get('per_page', 15);

            // Validate per_page parameter
            if ($perPage > 50) {
                $perPage = 50; // Maximum 50 items per page
            }

            // Query recent download sessions with download options
            $downloadSessions = DownloadSession::query()
                ->where('user_id', $user->id)
                ->with(['downloadOptions'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Transform data using resource
            $downloadSessionsData = DownloadSessionResource::collection($downloadSessions);

            Log::info('Recent downloads retrieved', [
                'user_id' => $user->id,
                'total_sessions' => $downloadSessions->total(),
                'current_page' => $downloadSessions->currentPage(),
                'per_page' => $downloadSessions->perPage(),
                'ip_address' => $request->ip(),
            ]);

            return $this->apiSuccessResponse([
                'download_sessions' => $downloadSessionsData,
                'pagination' => [
                    'current_page' => $downloadSessions->currentPage(),
                    'last_page' => $downloadSessions->lastPage(),
                    'per_page' => $downloadSessions->perPage(),
                    'total' => $downloadSessions->total(),
                    'from' => $downloadSessions->firstItem(),
                    'to' => $downloadSessions->lastItem(),
                    'has_more_pages' => $downloadSessions->hasMorePages(),
                ],
            ], __('Recent downloads retrieved successfully.'));

        } catch (Exception $e) {
            Log::error('Failed to retrieve recent downloads', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to retrieve recent downloads.'),
                null,
                500
            );
        }
    }
}
