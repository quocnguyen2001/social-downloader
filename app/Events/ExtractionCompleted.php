<?php

namespace App\Events;

use App\Models\DownloadSession;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when video extraction is completed successfully.
 * 
 * This event is dispatched when a video extraction job completes
 * successfully and contains the extraction results.
 */
class ExtractionCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param DownloadSession $downloadSession The completed download session
     * @param ExtractionResult $extractionResult The extraction results
     * @param float $processingTime The time taken to process the extraction (in seconds)
     * @param array $metadata Additional metadata about the extraction
     */
    public function __construct(
        public DownloadSession $downloadSession,
        public ExtractionResult $extractionResult,
        public float $processingTime,
        public array $metadata = []
    ) {}

    /**
     * Get the download session.
     *
     * @return DownloadSession
     */
    public function getDownloadSession(): DownloadSession
    {
        return $this->downloadSession;
    }

    /**
     * Get the extraction result.
     *
     * @return ExtractionResult
     */
    public function getExtractionResult(): ExtractionResult
    {
        return $this->extractionResult;
    }

    /**
     * Get the processing time.
     *
     * @return float
     */
    public function getProcessingTime(): float
    {
        return $this->processingTime;
    }

    /**
     * Get additional metadata.
     *
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the download session ID.
     *
     * @return string
     */
    public function getDownloadSessionId(): string
    {
        return $this->downloadSession->id;
    }

    /**
     * Get the original URL.
     *
     * @return string
     */
    public function getOriginalUrl(): string
    {
        return $this->downloadSession->original_url;
    }

    /**
     * Get the platform.
     *
     * @return \App\Enums\Platform
     */
    public function getPlatform(): \App\Enums\Platform
    {
        return $this->downloadSession->platform;
    }

    /**
     * Get the video title.
     *
     * @return string|null
     */
    public function getVideoTitle(): ?string
    {
        return $this->extractionResult->getTitle();
    }

    /**
     * Get the download URL.
     *
     * @return string|null
     */
    public function getDownloadUrl(): ?string
    {
        return $this->extractionResult->getDownloadUrl();
    }

    /**
     * Get the file size.
     *
     * @return int|null
     */
    public function getFileSize(): ?int
    {
        return $this->extractionResult->getFileSize();
    }

    /**
     * Get the video duration.
     *
     * @return int|null
     */
    public function getDuration(): ?int
    {
        return $this->extractionResult->getDuration();
    }

    /**
     * Check if the extraction was successful.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->downloadSession->status->value === 'completed' &&
               $this->extractionResult->getDownloadUrl() !== null;
    }

    /**
     * Get performance metrics.
     *
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'processing_time' => $this->processingTime,
            'file_size' => $this->getFileSize(),
            'duration' => $this->getDuration(),
            'platform' => $this->getPlatform()->value,
            'quality' => $this->downloadSession->quality->value,
            'format' => $this->downloadSession->format->value,
        ];
    }

    /**
     * Convert the event to an array for logging.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'download_session_id' => $this->downloadSession->id,
            'original_url' => $this->getOriginalUrl(),
            'platform' => $this->getPlatform()->value,
            'video_title' => $this->getVideoTitle(),
            'download_url' => $this->getDownloadUrl(),
            'file_size' => $this->getFileSize(),
            'duration' => $this->getDuration(),
            'processing_time' => $this->processingTime,
            'quality' => $this->downloadSession->quality->value,
            'format' => $this->downloadSession->format->value,
            'api_key_id' => $this->downloadSession->api_key_id,
            'metadata' => $this->metadata,
        ];
    }
}
