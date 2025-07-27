<?php

namespace App\Jobs;

use App\Enums\DownloadSessionStatus;
use App\Events\ExtractionCompleted;
use App\Events\ExtractionFailed;
use App\Models\ApiRequest;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\Factory\DriverFactory;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for processing video extraction requests.
 *
 * This job handles the main video extraction workflow, including
 * driver selection, metadata extraction, and result storage.
 */
class ProcessVideoExtraction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 30;

    /**
     * Create a new job instance.
     *
     * @param  string  $downloadSessionId  The download session ID to process
     * @param  array  $options  Additional processing options
     */
    public function __construct(
        private string $downloadSessionId,
        private array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DriverFactory $driverFactory): void
    {
        $startTime = microtime(true);

        Log::info('Starting video extraction job', [
            'download_session_id' => $this->downloadSessionId,
            'attempt' => $this->attempts(),
            'options' => $this->options,
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Update status to processing
            $this->updateSessionStatus($downloadSession, DownloadSessionStatus::FETCHING_METADATA);

            // Create driver and extract metadata
            $driver = $driverFactory->create($downloadSession->original_url);

            Log::info('Driver created for extraction', [
                'download_session_id' => $this->downloadSessionId,
                'platform' => $driver->getPlatform()->value,
                'driver_class' => get_class($driver),
            ]);

            $extractionResult = $driver->extractMetadata(
                $downloadSession->original_url,
                array_merge($this->options, [
                    'quality' => $downloadSession->quality,
                    'format' => $downloadSession->format,
                ])
            );

            // Update download session with extraction results
            $this->updateSessionWithResults($downloadSession, $extractionResult);

            // Log API request for billing
            $this->logApiRequest($downloadSession, $startTime);

            // Update status to completed
            $this->updateSessionStatus($downloadSession, DownloadSessionStatus::METADATA_FETCHED);

            $processingTime = microtime(true) - $startTime;

            Log::info('Video extraction completed successfully', [
                'download_session_id' => $this->downloadSessionId,
                'processing_time' => $processingTime,
                'video_title' => $extractionResult->getTitle(),
            ]);

            // Fire completion event
            ExtractionCompleted::dispatch(
                $downloadSession->fresh(),
                $extractionResult,
                $processingTime,
                [
                    'driver_class' => get_class($driver),
                    'attempt_number' => $this->attempts(),
                ]
            );

        } catch (\Throwable $exception) {
            $this->handleFailure($exception, $startTime);
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video extraction job failed permanently', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Update session status to failed
            $downloadSession->update([
                'status' => DownloadSessionStatus::FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            // Fire failure event
            ExtractionFailed::dispatch(
                $downloadSession,
                $exception,
                0, // No processing time available
                $this->attempts(),
                false, // Will not retry
                ['final_failure' => true]
            );

        } catch (\Exception $e) {
            Log::error('Failed to update session after job failure', [
                'download_session_id' => $this->downloadSessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the download session.
     */
    private function getDownloadSession(): DownloadSession
    {
        $session = DownloadSession::find($this->downloadSessionId);

        if (! $session) {
            throw new \RuntimeException("Download session not found: {$this->downloadSessionId}");
        }

        return $session;
    }

    /**
     * Update session status.
     */
    private function updateSessionStatus(DownloadSession $session, DownloadSessionStatus $status): void
    {
        $session->update(['status' => $status]);

        Log::debug('Download session status updated', [
            'download_session_id' => $session->id,
            'status' => $status->value,
        ]);
    }

    /**
     * Update session with extraction results.
     */
    private function updateSessionWithResults(DownloadSession $session, $extractionResult): void
    {
        $updateData = [
            'video_id' => $extractionResult->getVideoId(),
            'title' => $extractionResult->getTitle(),
            'thumbnail_url' => $extractionResult->getThumbnail(),
            'duration' => $extractionResult->getDuration(),
            'file_size' => $extractionResult->getFileSize(),
            'download_url' => $extractionResult->getDownloadUrl(),
            'expires_at' => Carbon::now()->addHours(24), // Download URL expires in 24 hours
        ];

        $session->update(array_filter($updateData, fn ($value) => $value !== null));

        Log::debug('Download session updated with extraction results', [
            'download_session_id' => $session->id,
            'title' => $extractionResult->getTitle(),
            'file_size' => $extractionResult->getFileSize(),
        ]);
    }

    /**
     * Log API request for billing purposes.
     */
    private function logApiRequest(DownloadSession $session, float $startTime): void
    {
        if (! $session->api_key_id) {
            return; // No API key, no billing
        }

        $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        ApiRequest::create([
            'api_key_id' => $session->api_key_id,
            'endpoint' => '/api/extract',
            'method' => 'POST',
            'ip_address' => $this->options['ip_address'] ?? null,
            'user_agent' => $this->options['user_agent'] ?? null,
            'original_url' => $session->original_url,
            'platform' => $session->platform->value,
            'video_title' => $session->title,
            'requested_quality' => $session->quality->value,
            'requested_format' => $session->format->value,
            'status_code' => 200,
            'response_time' => (int) $processingTime,
            'file_size' => $session->file_size,
            'download_url' => $session->download_url,
            'cost' => $this->calculateCost($session),
            'billed' => false, // Will be processed by billing job
        ]);

        Log::debug('API request logged for billing', [
            'download_session_id' => $session->id,
            'api_key_id' => $session->api_key_id,
            'processing_time' => $processingTime,
        ]);
    }

    /**
     * Calculate the cost for this extraction.
     */
    private function calculateCost(DownloadSession $session): float
    {
        // Basic cost calculation - can be made more sophisticated
        $baseCost = 0.01; // $0.01 per extraction

        // Quality multiplier
        $qualityMultiplier = match ($session->quality->value) {
            '1080p' => 2.0,
            '720p' => 1.5,
            '360p' => 1.0,
            '144p' => 0.5,
            default => 1.0,
        };

        return $baseCost * $qualityMultiplier;
    }

    /**
     * Handle extraction failure.
     */
    private function handleFailure(\Throwable $exception, float $startTime): void
    {
        $processingTime = microtime(true) - $startTime;
        $willRetry = $this->attempts() < $this->tries;

        Log::warning('Video extraction attempt failed', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
            'attempt' => $this->attempts(),
            'will_retry' => $willRetry,
            'processing_time' => $processingTime,
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Update error message but don't change status if we're retrying
            if (! $willRetry) {
                $downloadSession->update([
                    'status' => DownloadSessionStatus::FAILED,
                    'error_message' => $exception->getMessage(),
                ]);
            } else {
                $downloadSession->update([
                    'error_message' => "Attempt {$this->attempts()}: ".$exception->getMessage(),
                ]);
            }

            // Fire failure event
            ExtractionFailed::dispatch(
                $downloadSession,
                $exception,
                $processingTime,
                $this->attempts(),
                $willRetry,
                [
                    'job_class' => static::class,
                    'options' => $this->options,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Failed to handle extraction failure', [
                'download_session_id' => $this->downloadSessionId,
                'original_exception' => $exception->getMessage(),
                'handling_exception' => $e->getMessage(),
            ]);
        }

        // Re-throw the exception to trigger Laravel's retry mechanism
        throw $exception;
    }
}
