<?php

namespace App\Services\VideoExtraction\Exceptions;

use Exception;

/**
 * Exception thrown when yt-dlp operations fail.
 */
class YtDlpException extends Exception
{
    /**
     * The exit code from yt-dlp command.
     */
    private ?int $exitCode;

    public function __construct(string $message = '', ?int $exitCode = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->exitCode = $exitCode;
    }

    /**
     * Get the yt-dlp exit code.
     */
    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    /**
     * Check if the error is due to unsupported URL.
     */
    public function isUnsupportedUrl(): bool
    {
        return str_contains($this->getMessage(), 'Unsupported URL') ||
               str_contains($this->getMessage(), 'No video formats found') ||
               $this->exitCode === 1;
    }

    /**
     * Check if the error is due to network issues.
     */
    public function isNetworkError(): bool
    {
        return str_contains($this->getMessage(), 'network') ||
               str_contains($this->getMessage(), 'timeout') ||
               str_contains($this->getMessage(), 'connection') ||
               $this->exitCode === 2;
    }

    /**
     * Check if the error is due to video being private or unavailable.
     */
    public function isVideoUnavailable(): bool
    {
        return str_contains($this->getMessage(), 'Private video') ||
               str_contains($this->getMessage(), 'Video unavailable') ||
               str_contains($this->getMessage(), 'This video is not available') ||
               str_contains($this->getMessage(), 'Login required') ||
               str_contains($this->getMessage(), 'Sign up to see') ||
               str_contains($this->getMessage(), 'Content not available') ||
               $this->exitCode === 3;
    }

    /**
     * Check if the error is due to Instagram-specific blocking.
     */
    public function isInstagramBlocked(): bool
    {
        return str_contains($this->getMessage(), 'instagram') &&
               (str_contains($this->getMessage(), 'blocked') ||
                str_contains($this->getMessage(), 'rate limit') ||
                str_contains($this->getMessage(), '429') ||
                str_contains($this->getMessage(), 'Too Many Requests'));
    }

    /**
     * Get a user-friendly error message.
     */
    public function getUserFriendlyMessage(): string
    {
        if ($this->isUnsupportedUrl()) {
            return __('video_extraction.errors.unsupported_url');
        }

        if ($this->isNetworkError()) {
            return __('video_extraction.errors.network_error');
        }

        if ($this->isVideoUnavailable()) {
            return __('video_extraction.errors.video_unavailable');
        }

        return __('video_extraction.errors.general_error');
    }
}
