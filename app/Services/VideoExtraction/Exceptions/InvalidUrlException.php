<?php

namespace App\Services\VideoExtraction\Exceptions;

/**
 * Exception thrown when a URL is invalid or malformed.
 */
class InvalidUrlException extends VideoExtractionException
{
    /**
     * Create a new invalid URL exception.
     *
     * @param  string  $url  The invalid URL
     * @param  string|null  $reason  The reason why the URL is invalid
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $url,
        ?string $reason = null,
        ?\Throwable $previous = null
    ) {
        $message = "Invalid URL: {$url}";

        if ($reason) {
            $message .= " - {$reason}";
        }

        parent::__construct(
            message: $message,
            code: 1003,
            previous: $previous,
            context: [
                'url' => $url,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Get the invalid URL.
     */
    public function getUrl(): string
    {
        return $this->context['url'];
    }

    /**
     * Get the reason why the URL is invalid.
     */
    public function getReason(): ?string
    {
        return $this->context['reason'] ?? null;
    }
}
