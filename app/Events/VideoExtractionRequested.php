<?php

namespace App\Events;

use App\Enums\Platform;
use App\Models\ApiKey;
use App\Models\DownloadSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a video extraction is requested.
 *
 * This event is dispatched when a user requests video extraction
 * and contains all the necessary information to process the request.
 */
class VideoExtractionRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  DownloadSession  $downloadSession  The download session being processed
     * @param  string  $originalUrl  The original video URL
     * @param  Platform  $platform  The detected platform
     * @param  ApiKey|null  $apiKey  The API key used for the request (if any)
     * @param  array  $options  Additional extraction options
     */
    public function __construct(
        public DownloadSession $downloadSession,
        public string $originalUrl,
        public Platform $platform,
        public ?ApiKey $apiKey = null,
        public array $options = []
    ) {}

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
        return $this->originalUrl;
    }

    /**
     * Get the detected platform.
     */
    public function getPlatform(): Platform
    {
        return $this->platform;
    }

    /**
     * Get the API key (if any).
     */
    public function getApiKey(): ?ApiKey
    {
        return $this->apiKey;
    }

    /**
     * Get additional extraction options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Check if the request has an API key.
     */
    public function hasApiKey(): bool
    {
        return $this->apiKey !== null;
    }

    /**
     * Get the user agent from options.
     */
    public function getUserAgent(): ?string
    {
        return $this->options['user_agent'] ?? null;
    }

    /**
     * Get the IP address from options.
     */
    public function getIpAddress(): ?string
    {
        return $this->options['ip_address'] ?? null;
    }

    /**
     * Get the priority for job processing.
     */
    public function getPriority(): int
    {
        // Higher priority for API key users
        if ($this->hasApiKey()) {
            return match ($this->apiKey->tier) {
                'premium' => 10,
                'pro' => 5,
                default => 1,
            };
        }

        return 0; // Default priority for guest users
    }

    /**
     * Convert the event to an array for logging.
     */
    public function toArray(): array
    {
        return [
            'download_session_id' => $this->downloadSession->id,
            'original_url' => $this->originalUrl,
            'platform' => $this->platform->value,
            'api_key_id' => $this->apiKey?->id,
            'api_key_tier' => $this->apiKey?->tier,
            'priority' => $this->getPriority(),
            'options' => $this->options,
        ];
    }
}
