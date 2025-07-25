<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Mail\PasswordChangedEmail;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\OTPService;
use App\Services\RateLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Authentication Controller for API endpoints.
 *
 * Handles user registration, login, password reset, and token management
 * using Laravel Sanctum for API authentication.
 */
class AuthController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private RateLimitService $rateLimitService,
        private OTPService $otpService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Check rate limiting
            $rateLimitCheck = $this->rateLimitService->checkRateLimit(
                'registration',
                $request,
                $this->rateLimitService->getUserStrategy($request)
            );

            if ($rateLimitCheck['limited']) {
                return $this->rateLimitResponse(
                    $this->rateLimitService->getRateLimitMessage('registration', $rateLimitCheck['seconds']),
                    $rateLimitCheck['seconds']
                );
            }

            // Validation is handled by RegisterRequest

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Generate API token
            $token = $user->createToken('auth-token', ['*'], now()->addMinutes(config('sanctum.expiration', 10080)));

            // Clear rate limiting on successful registration
            $this->rateLimitService->clear($rateLimitCheck['key']);

            // Log the registration
            Log::info('User registered successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send welcome email
            try {
                Mail::to($user)->send(new WelcomeEmail($user));
                Log::info('Welcome email sent', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail registration if email fails
            }

            return $this->createdResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'created_at' => $user->created_at,
                ],
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ], trans('auth.messages.registration_success'));

        } catch (\Exception $e) {
            // Hit rate limiter on registration failure
            $config = config('api_rate_limits.authentication.registration');
            $this->rateLimitService->hit($rateLimitCheck['key'], $config['decay_minutes']);

            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip_address' => $request->ip(),
            ]);

            return $this->serverErrorResponse(
                trans('auth.messages.registration_error')
            );
        }
    }

    /**
     * Authenticate user and return token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            // Rate limiting
            $key = 'login_attempts:'.$request->ip();
            $maxAttempts = 5;
            $decayMinutes = 15;

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);

                return $this->rateLimitResponse(
                    trans('auth.throttle', ['seconds' => $seconds]),
                    $seconds
                );
            }

            // Validation is handled by LoginRequest

            // Attempt authentication
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                RateLimiter::hit($key, $decayMinutes * 60);

                return $this->unauthorizedResponse(
                    trans('auth.messages.login_failed')
                );
            }

            // Clear rate limiting on successful login
            RateLimiter::clear($key);

            // Update last login timestamp
            $user->updateLastLogin();

            // Generate API token
            $deviceName = $request->device_name ?? $request->userAgent() ?? 'Unknown Device';
            $token = $user->createToken($deviceName, ['*'], now()->addMinutes(config('sanctum.expiration', 10080)));

            // Log the login
            Log::info('User logged in successfully', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_name' => $deviceName,
            ]);

            // Optional: Send login notification email (can be enabled via config)
            if (config('auth.send_login_notifications', false)) {
                try {
                    // TODO: Create LoginNotificationEmail if needed
                    Log::info('Login notification email would be sent', ['user_id' => $user->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send login notification email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                ],
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $token->accessToken->expires_at,
            ], trans('auth.messages.login_success'));

        } catch (\Exception $e) {
            Log::error('Login failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip_address' => $request->ip(),
            ]);

            return $this->serverErrorResponse(
                trans('auth.messages.login_error')
            );
        }
    }

    /**
     * Send OTP for password reset.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            // Rate limiting for password reset requests
            $key = 'password_reset:'.$request->ip();
            $maxAttempts = 3;
            $decayMinutes = 60;

            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($key);

                return $this->apiErrorResponse(
                    __('Too many password reset attempts. Please try again later.'),
                    null,
                    429
                );
            }

            // Validation is handled by ForgotPasswordRequest
            $email = $request->email;

            // Check if user exists
            $user = User::where('email', $email)->first();
            if (! $user) {
                // Don't reveal if user exists or not for security
                return $this->apiSuccessResponse(
                    null,
                    __('If an account with that email exists, we have sent a password reset OTP.')
                );
            }

            // Generate OTP
            $otp = $this->otpService->generate($email, 'password_reset');

            // TODO: Send OTP via email (implement email sending)
            // For now, we'll just log it (remove this in production)
            Log::info('Password reset OTP generated', [
                'email' => $email,
                'otp' => $otp, // Remove this in production
                'expires_in_minutes' => $this->otpService->getExpirationMinutes(),
                'ip_address' => $request->ip(),
            ]);

            return $this->apiSuccessResponse(
                [
                    'expires_in_minutes' => $this->otpService->getExpirationMinutes(),
                ],
                __('If an account with that email exists, we have sent a password reset OTP.')
            );

        } catch (\Exception $e) {
            // Hit rate limiter on failure
            RateLimiter::hit($key, $decayMinutes * 60);

            Log::error('Password reset OTP generation failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip_address' => $request->ip(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to process password reset request. Please try again later.'),
                null,
                500
            );
        }
    }

    /**
     * Reset password with token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            // Validation is handled by ResetPasswordRequest

            // Reset the password
            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function (User $user, string $password): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    // Revoke all existing tokens for security
                    $user->tokens()->delete();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                // Log the password reset
                Log::info('Password reset successfully', [
                    'email' => $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);

                // Send password changed confirmation email
                try {
                    $user = User::where('email', $request->email)->first();
                    if ($user) {
                        Mail::to($user)->send(new PasswordChangedEmail($user));
                        Log::info('Password changed email sent', ['user_id' => $user->id]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to send password changed email', [
                        'email' => $request->email,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail password reset if email fails
                }

                return $this->successResponse(
                    [],
                    trans('auth.messages.password_reset_success')
                );
            }

            // Handle different failure statuses
            $message = match ($status) {
                Password::INVALID_TOKEN => trans('auth.validation.token_invalid'),
                Password::INVALID_USER => trans('auth.messages.user_not_found'),
                default => trans('auth.messages.password_reset_error'),
            };

            return $this->errorResponse($message, [], 400);

        } catch (\Exception $e) {
            Log::error('Password reset failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse(
                trans('auth.messages.password_reset_error'),
                [],
                500
            );
        }
    }

    /**
     * Reset password using OTP.
     */
    public function newPassword(NewPasswordRequest $request): JsonResponse
    {
        try {
            $email = $request->email;
            $otp = $request->otp;
            $password = $request->password;

            // Validate OTP
            if (! $this->otpService->validate($email, $otp, 'password_reset')) {
                return $this->apiErrorResponse(
                    __('Invalid or expired OTP. Please request a new password reset.'),
                    null,
                    400
                );
            }

            // Find user
            $user = User::where('email', $email)->first();
            if (! $user) {
                return $this->apiErrorResponse(
                    __('User not found.'),
                    null,
                    404
                );
            }

            // Update password
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Log the password reset
            Log::info('Password reset successfully using OTP', [
                'user_id' => $user->id,
                'email' => $email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Send password changed confirmation email
            try {
                Mail::to($user)->send(new PasswordChangedEmail($user));
                Log::info('Password changed email sent', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('Failed to send password changed email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail password reset if email fails
            }

            return $this->apiSuccessResponse(
                null,
                __('Password has been reset successfully.')
            );

        } catch (\Exception $e) {
            Log::error('Password reset with OTP failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip_address' => $request->ip(),
            ]);

            return $this->apiErrorResponse(
                __('Failed to reset password. Please try again.'),
                null,
                500
            );
        }
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Get the current token
            $currentToken = $request->user()->currentAccessToken();

            // Option to logout from all devices or just current device
            $logoutAll = $request->boolean('logout_all', false);

            if ($logoutAll) {
                // Revoke all tokens
                $user->tokens()->delete();
                $message = trans('auth.messages.logout_success').' (all devices)';
            } else {
                // Revoke only current token
                $currentToken?->delete();
                $message = trans('auth.messages.logout_success');
            }

            // Log the logout
            Log::info('User logged out', [
                'user_id' => $user->id,
                'email' => $user->email,
                'logout_all' => $logoutAll,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([], $message);

        } catch (\Exception $e) {
            Log::error('Logout failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse(
                'Logout failed. Please try again.',
                [],
                500
            );
        }
    }

    /**
     * Get authenticated user information.
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $user->currentAccessToken();

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                    'last_login_at' => $user->last_login_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'membership_plan' => $user->membershipPlan ? [
                        'id' => $user->membershipPlan->id,
                        'name' => $user->membershipPlan->name,
                        'slug' => $user->membershipPlan->slug,
                    ] : null,
                    'membership_expires_at' => $user->membership_expires_at,
                    'active_tokens_count' => $user->tokens()->count(),
                ],
                'current_token' => [
                    'id' => $currentToken->id,
                    'name' => $currentToken->name,
                    'abilities' => $currentToken->abilities,
                    'created_at' => $currentToken->created_at,
                    'last_used_at' => $currentToken->last_used_at,
                    'expires_at' => $currentToken->expires_at,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user information', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse(
                'Failed to retrieve user information.',
                [],
                500
            );
        }
    }
}
