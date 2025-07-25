<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

            // Transform user data using resource
            $userData = new UserResource($user);

            Log::info('User profile retrieved', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return $this->apiSuccessResponse(
                $userData,
                __('User profile retrieved successfully.')
            );

        } catch (\Exception $e) {
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

        } catch (\Exception $e) {
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
}
