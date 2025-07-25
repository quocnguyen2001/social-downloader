<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use App\Settings\GeneralSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Settings Controller for API endpoints.
 *
 * Handles public access to application settings.
 */
class SettingsController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all general settings.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $settings = app(GeneralSettings::class);

            // Prepare settings data for API response
            $settingsData = [
                'site_name' => $settings->site_name,
                'site_description' => $settings->site_description,
                'site_url' => $settings->site_url,
                'admin_email' => $settings->admin_email,
                'support_email' => $settings->support_email,
                'default_timezone' => $settings->default_timezone,
                'maintenance_mode' => $settings->maintenance_mode,
                'maintenance_message' => $settings->maintenance_message,
                'terms_of_service_url' => $settings->terms_of_service_url,
                'privacy_policy_url' => $settings->privacy_policy_url,
                'meta_title' => $settings->meta_title,
                'meta_description' => $settings->meta_description,
                'meta_keywords' => $settings->meta_keywords,
                'copyright_text' => $settings->copyright_text,
                'copyright_year' => $settings->copyright_year,
                'logo_path' => $settings->logo_path ? asset('storage/'.$settings->logo_path) : null,
                'favicon_path' => $settings->favicon_path ? asset('storage/'.$settings->favicon_path) : null,
            ];

            Log::info('Settings retrieved via API', [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiSuccessResponse(
                $settingsData,
                __('Settings retrieved successfully.')
            );

        } catch (\Exception $e) {
            Log::error('Failed to retrieve settings via API', [
                'error' => $e->getMessage(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to retrieve settings.'),
                null,
                500
            );
        }
    }
}
