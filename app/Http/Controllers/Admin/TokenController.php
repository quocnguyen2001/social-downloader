<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * Revoke a specific token.
     */
    public function revoke(string $tokenId): JsonResponse
    {
        try {
            $token = PersonalAccessToken::query()->findOrFail($tokenId);
            $tokenName = $token->name;

            $token->delete();

            return response()->json([
                'success' => true,
                'message' => "Token '{$tokenName}' has been revoked successfully.",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke token.',
            ], 500);
        }
    }
}
