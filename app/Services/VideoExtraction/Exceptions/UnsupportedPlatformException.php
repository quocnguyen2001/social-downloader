<?php

namespace App\Services\VideoExtraction\Exceptions;

/**
 * Exception thrown when a URL belongs to an unsupported platform.
 */
class UnsupportedPlatformException extends VideoExtractionException
{
    /**
     * Create a new unsupported platform exception.
     *
     * @param  string  $url  The unsupported URL
     * @param  string|null  $detectedPlatform  The detected platform (if any)
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        string $url,
        ?string $detectedPlatform = null,
        ?\Throwable $previous = null
    ) {
        $message = "Unsupported platform for URL: {$url}";

        if ($detectedPlatform) {
            $message .= " (detected platform: {$detectedPlatform})";
        }

        parent::__construct(
            message: $message,
            code: 1001,
            previous: $previous,
            context: [
                'url' => $url,
                'detected_platform' => $detectedPlatform,
            ]
        );
    }

    /**
     * Get the unsupported URL.
     */
    public function getUrl(): string
    {
        return $this->context['url'];
    }

    /**
     * Get the detected platform (if any).
     */
    public function getDetectedPlatform(): ?string
    {
        return $this->context['detected_platform'] ?? null;
    }
}
