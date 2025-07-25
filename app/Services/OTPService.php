<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OTP Service for generating and validating one-time passwords.
 *
 * Handles OTP generation, validation, and cleanup for various use cases
 * like password reset, email verification, etc.
 */
class OTPService
{
    /**
     * OTP expiration time in minutes.
     */
    private const EXPIRATION_MINUTES = 10;

    /**
     * Maximum OTP generation attempts per email per hour.
     */
    private const MAX_ATTEMPTS_PER_HOUR = 5;

    /**
     * Generate a new OTP for the given email and type.
     *
     * @return string The generated OTP code
     *
     * @throws \Exception If rate limit exceeded
     */
    public function generate(string $email, string $type = 'password_reset'): string
    {
        // Check rate limiting
        $this->checkRateLimit($email, $type);

        // Generate 6-digit OTP
        $otpCode = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Invalidate any existing OTPs for this email and type
        $this->invalidateExisting($email, $type);

        // Store new OTP
        DB::table('otps')->insert([
            'email' => $email,
            'otp_code' => $otpCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(self::EXPIRATION_MINUTES),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        Log::info('OTP generated', [
            'email' => $email,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(self::EXPIRATION_MINUTES),
        ]);

        return $otpCode;
    }

    /**
     * Validate an OTP for the given email and type.
     */
    public function validate(string $email, string $otp, string $type = 'password_reset'): bool
    {
        $otpRecord = DB::table('otps')
            ->where('email', $email)
            ->where('otp_code', $otp)
            ->where('type', $type)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (! $otpRecord) {
            Log::warning('OTP validation failed', [
                'email' => $email,
                'type' => $type,
                'reason' => 'OTP not found or expired',
            ]);

            return false;
        }

        // Mark OTP as used
        DB::table('otps')
            ->where('id', $otpRecord->id)
            ->update([
                'used_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        Log::info('OTP validated successfully', [
            'email' => $email,
            'type' => $type,
        ]);

        return true;
    }

    /**
     * Check if the email has exceeded the rate limit for OTP generation.
     *
     * @throws \Exception If rate limit exceeded
     */
    private function checkRateLimit(string $email, string $type): void
    {
        $count = DB::table('otps')
            ->where('email', $email)
            ->where('type', $type)
            ->where('created_at', '>', Carbon::now()->subHour())
            ->count();

        if ($count >= self::MAX_ATTEMPTS_PER_HOUR) {
            Log::warning('OTP rate limit exceeded', [
                'email' => $email,
                'type' => $type,
                'attempts' => $count,
            ]);

            throw new \Exception(__('Too many OTP requests. Please try again later.'));
        }
    }

    /**
     * Invalidate existing unused OTPs for the given email and type.
     */
    private function invalidateExisting(string $email, string $type): void
    {
        DB::table('otps')
            ->where('email', $email)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update([
                'used_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Clean up expired OTPs from the database.
     *
     * This method should be called periodically (e.g., via a scheduled job).
     */
    public function cleanup(): void
    {
        $deletedCount = DB::table('otps')
            ->where('expires_at', '<', Carbon::now()->subDay())
            ->delete();

        if ($deletedCount > 0) {
            Log::info('OTP cleanup completed', [
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * Get the expiration time in minutes.
     */
    public function getExpirationMinutes(): int
    {
        return self::EXPIRATION_MINUTES;
    }
}
