<?php

namespace App\Services\VideoExtraction\Exceptions;

use App\Enums\Platform;

/**
 * Exception thrown when video extraction fails.
 */
class ExtractionFailedException extends VideoExtractionException
{
    /**
     * Create a new extraction failed exception.
     *
     * @param  string  $url  The URL that failed to extract
     * @param  Platform|null  $platform  The platform being extracted from
     * @param  string|null  $reason  The reason for failure
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $url,
        ?Platform $platform = null,
        ?string $reason = null,
        ?\Throwable $previous = null
    ) {
        $message = "Failed to extract video from URL: {$url}";

        if ($platform) {
            $message .= " (platform: {$platform->value})";
        }

        if ($reason) {
            $message .= " - {$reason}";
        }

        parent::__construct(
            message: $message,
            code: 1002,
            previous: $previous,
            context: [
                'url' => $url,
                'platform' => $platform?->value,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Get the URL that failed to extract.
     */
    public function getUrl(): string
    {
        return $this->context['url'];
    }

    /**
     * Get the platform being extracted from.
     */
    public function getPlatform(): ?Platform
    {
        $platformValue = $this->context['platform'] ?? null;

        return $platformValue ? Platform::from($platformValue) : null;
    }

    /**
     * Get the reason for failure.
     */
    public function getReason(): ?string
    {
        return $this->context['reason'] ?? null;
    }
}
