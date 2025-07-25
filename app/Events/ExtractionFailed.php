<?php

namespace App\Events;

use App\Models\DownloadSession;
use App\Services\VideoExtraction\Exceptions\VideoExtractionException;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when video extraction fails.
 *
 * This event is dispatched when a video extraction job fails
 * and contains information about the failure.
 */
class ExtractionFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DownloadSession  $downloadSession  The failed download session
     * @param  \Throwable  $exception  The exception that caused the failure
     * @param  float  $processingTime  The time taken before failure (in seconds)
     * @param  int  $attemptNumber  The attempt number (for retries)
     * @param  bool  $willRetry  Whether the job will be retried
     * @param  array  $metadata  Additional metadata about the failure
     */
    public function __construct(
        public DownloadSession $downloadSession,
        public \Throwable $exception,
        public float $processingTime,
        public int $attemptNumber = 1,
        public bool $willRetry = false,
        public array $metadata = []
    ) {}

    /**
     * Get the download session.
     */
    public function getDownloadSession(): DownloadSession
    {
        return $this->downloadSession;
    }

    /**
     * Get the exception that caused the failure.
     */
    public function getException(): \Throwable
    {
        return $this->exception;
    }

    /**
     * Get the processing time before failure.
     */
    public function getProcessingTime(): float
    {
        return $this->processingTime;
    }

    /**
     * Get the attempt number.
     */
    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    /**
     * Check if the job will be retried.
     */
    public function willRetry(): bool
    {
        return $this->willRetry;
    }

    /**
     * Get additional metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the download session ID.
     */
    public function getDownloadSessionId(): string
    {
        return $this->downloadSession->id;
    }

    /**
     * Get the original URL.
     */
    public function getOriginalUrl(): string
    {
        return $this->downloadSession->original_url;
    }

    /**
     * Get the platform.
     */
    public function getPlatform(): \App\Enums\Platform
    {
        return $this->downloadSession->platform;
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the error code.
     */
    public function getErrorCode(): int
    {
        return $this->exception->getCode();
    }

    /**
     * Check if the exception is a video extraction exception.
     */
    public function isVideoExtractionException(): bool
    {
        return $this->exception instanceof VideoExtractionException;
    }

    /**
     * Get the exception context (if it's a VideoExtractionException).
     */
    public function getExceptionContext(): array
    {
        if ($this->isVideoExtractionException()) {
            /** @var VideoExtractionException $exception */
            $exception = $this->exception;

            return $exception->getContext();
        }

        return [];
    }

    /**
     * Get the failure category.
     */
    public function getFailureCategory(): string
    {
        if ($this->isVideoExtractionException()) {
            return match (get_class($this->exception)) {
                \App\Services\VideoExtraction\Exceptions\UnsupportedPlatformException::class => 'unsupported_platform',
                \App\Services\VideoExtraction\Exceptions\InvalidUrlException::class => 'invalid_url',
                \App\Services\VideoExtraction\Exceptions\RateLimitExceededException::class => 'rate_limit',
                \App\Services\VideoExtraction\Exceptions\ExtractionFailedException::class => 'extraction_failed',
                default => 'video_extraction_error',
            };
        }

        return match (true) {
            $this->exception instanceof \InvalidArgumentException => 'invalid_argument',
            $this->exception instanceof \RuntimeException => 'runtime_error',
            $this->exception instanceof \Exception => 'general_error',
            default => 'unknown_error',
        };
    }

    /**
     * Check if the failure is retryable.
     */
    public function isRetryable(): bool
    {
        // Don't retry certain types of errors
        $nonRetryableCategories = [
            'unsupported_platform',
            'invalid_url',
            'invalid_argument',
        ];

        return ! in_array($this->getFailureCategory(), $nonRetryableCategories);
    }

    /**
     * Get failure statistics.
     */
    public function getFailureStatistics(): array
    {
        return [
            'processing_time' => $this->processingTime,
            'attempt_number' => $this->attemptNumber,
            'will_retry' => $this->willRetry,
            'failure_category' => $this->getFailureCategory(),
            'error_code' => $this->getErrorCode(),
            'platform' => $this->getPlatform()->value,
            'is_retryable' => $this->isRetryable(),
        ];
    }

    /**
     * Convert the event to an array for logging.
     */
    public function toArray(): array
    {
        return [
            'download_session_id' => $this->downloadSession->id,
            'original_url' => $this->getOriginalUrl(),
            'platform' => $this->getPlatform()->value,
            'error_message' => $this->getErrorMessage(),
            'error_code' => $this->getErrorCode(),
            'exception_class' => get_class($this->exception),
            'failure_category' => $this->getFailureCategory(),
            'processing_time' => $this->processingTime,
            'attempt_number' => $this->attemptNumber,
            'will_retry' => $this->willRetry,
            'is_retryable' => $this->isRetryable(),
            'api_key_id' => $this->downloadSession->api_key_id,
            'exception_context' => $this->getExceptionContext(),
            'metadata' => $this->metadata,
        ];
    }
}
