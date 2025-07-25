<?php

namespace App\Listeners;

use App\Events\VideoExtractionRequested;
use App\Jobs\ExtractVideoMetadataJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener for handling video extraction requests.
 *
 * This listener responds to VideoExtractionRequested events by
 * dispatching the appropriate jobs to process the extraction.
 */
class HandleExtractionRequest implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the listener may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the listener can run.
     */
    public int $timeout = 60;

    /**
     * Handle the event.
     */
    public function handle(VideoExtractionRequested $event): void
    {
        Log::info('Handling video extraction request', [
            'download_session_id' => $event->getDownloadSessionId(),
            'platform' => $event->getPlatform()->value,
            'has_api_key' => $event->hasApiKey(),
            'priority' => $event->getPriority(),
        ]);

        try {
            // Prepare job options
            $jobOptions = [
                'ip_address' => $event->getIpAddress(),
                'user_agent' => $event->getUserAgent(),
                'platform' => $event->getPlatform()->value,
            ];

            // Dispatch the metadata extraction job
            $metadataJob = new ExtractVideoMetadataJob(
                $event->getDownloadSessionId(),
                $jobOptions
            );

            // Set job priority and queue based on API key tier
            $queueName = $this->determineQueue($event);
            $metadataJob->onQueue($queueName);

            // Dispatch the job
            dispatch($metadataJob);

            Log::info('Extraction jobs dispatched successfully', [
                'download_session_id' => $event->getDownloadSessionId(),
                'extraction_queue' => $queueName,
                'validation_delayed' => true,
            ]);

        } catch (\Throwable $exception) {
            Log::error('Failed to handle extraction request', [
                'download_session_id' => $event->getDownloadSessionId(),
                'exception' => $exception->getMessage(),
                'event_data' => $event->toArray(),
            ]);

            // Update the download session with the error
            try {
                $event->downloadSession->update([
                    'status' => \App\Enums\DownloadSessionStatus::FAILED,
                    'error_message' => 'Failed to dispatch extraction job: '.$exception->getMessage(),
                ]);
            } catch (\Exception $updateException) {
                Log::error('Failed to update session after listener error', [
                    'download_session_id' => $event->getDownloadSessionId(),
                    'update_error' => $updateException->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(VideoExtractionRequested $event, \Throwable $exception): void
    {
        Log::error('Extraction request listener failed permanently', [
            'download_session_id' => $event->getDownloadSessionId(),
            'exception' => $exception->getMessage(),
            'event_data' => $event->toArray(),
        ]);

        try {
            $event->downloadSession->update([
                'status' => \App\Enums\DownloadSessionStatus::FAILED,
                'error_message' => 'Extraction request handling failed: '.$exception->getMessage(),
            ]);
        } catch (\Exception $updateException) {
            Log::error('Failed to update session after permanent listener failure', [
                'download_session_id' => $event->getDownloadSessionId(),
                'update_error' => $updateException->getMessage(),
            ]);
        }
    }

    /**
     * Determine the appropriate queue for the extraction job.
     */
    private function determineQueue(VideoExtractionRequested $event): string
    {
        if (! $event->hasApiKey()) {
            return 'guest'; // Guest users get lower priority queue
        }

        $apiKey = $event->getApiKey();

        return match ($apiKey->tier ?? 'basic') {
            'premium' => 'premium',
            'pro' => 'pro',
            'basic' => 'basic',
            default => 'default',
        };
    }

    /**
     * Get the tags that should be assigned to the listener.
     */
    public function tags(): array
    {
        return ['extraction', 'listener', 'video'];
    }
}
