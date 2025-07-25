<?php

namespace App\Services\VideoExtraction\Exceptions;

use App\Enums\Platform;

/**
 * Exception thrown when rate limits are exceeded.
 */
class RateLimitExceededException extends VideoExtractionException
{
    /**
     * Create a new rate limit exceeded exception.
     *
     * @param  Platform|null  $platform  The platform that rate limited the request
     * @param  int|null  $retryAfter  The number of seconds to wait before retrying
     * @param  string|null  $reason  Additional reason for the rate limit
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        ?Platform $platform = null,
        ?int $retryAfter = null,
        ?string $reason = null,
        ?\Throwable $previous = null
    ) {
        $message = 'Rate limit exceeded';

        if ($platform) {
            $message .= " for platform: {$platform->value}";
        }

        if ($retryAfter) {
            $message .= ". Retry after {$retryAfter} seconds";
        }

        if ($reason) {
            $message .= " - {$reason}";
        }

        parent::__construct(
            message: $message,
            code: 1004,
            previous: $previous,
            context: [
                'platform' => $platform?->value,
                'retry_after' => $retryAfter,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Get the platform that rate limited the request.
     */
    public function getPlatform(): ?Platform
    {
        $platformValue = $this->context['platform'] ?? null;

        return $platformValue ? Platform::from($platformValue) : null;
    }

    /**
     * Get the number of seconds to wait before retrying.
     */
    public function getRetryAfter(): ?int
    {
        return $this->context['retry_after'] ?? null;
    }

    /**
     * Get the additional reason for the rate limit.
     */
    public function getReason(): ?string
    {
        return $this->context['reason'] ?? null;
    }
}
