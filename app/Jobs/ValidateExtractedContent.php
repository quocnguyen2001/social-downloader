<?php

namespace App\Jobs;

use App\Enums\DownloadSessionStatus;
use App\Models\DownloadSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job for validating extracted content.
 *
 * This job validates that extracted download URLs are still accessible
 * and that the content matches the expected metadata.
 */
class ValidateExtractedContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     *
     * @param  string  $downloadSessionId  The download session ID to validate
     * @param  array  $validationOptions  Validation options
     */
    public function __construct(
        private string $downloadSessionId,
        private array $validationOptions = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting content validation job', [
            'download_session_id' => $this->downloadSessionId,
            'options' => $this->validationOptions,
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Skip validation for certain statuses
            if (! $this->shouldValidate($downloadSession)) {
                Log::info('Content validation skipped', [
                    'download_session_id' => $this->downloadSessionId,
                    'status' => $downloadSession->status->value,
                ]);

                return;
            }

            $validationResults = $this->performValidation($downloadSession);

            // Update session based on validation results
            $this->updateSessionFromValidation($downloadSession, $validationResults);

            Log::info('Content validation completed', [
                'download_session_id' => $this->downloadSessionId,
                'results' => $validationResults,
            ]);

        } catch (\Throwable $exception) {
            Log::error('Content validation failed', [
                'download_session_id' => $this->downloadSessionId,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Content validation job failed permanently', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();

            // Mark validation as failed in error message
            $currentError = $downloadSession->error_message ?? '';
            $newError = $currentError ? $currentError.' | ' : '';
            $newError .= 'Content validation failed: '.$exception->getMessage();

            $downloadSession->update(['error_message' => $newError]);

        } catch (\Exception $e) {
            Log::error('Failed to update session after validation failure', [
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
     * Check if the session should be validated.
     */
    private function shouldValidate(DownloadSession $session): bool
    {
        // Only validate completed sessions
        if ($session->status !== DownloadSessionStatus::METADATA_FETCHED) {
            return false;
        }

        // Must have a download URL to validate
        if (! $session->download_url) {
            return false;
        }

        return true;
    }

    /**
     * Perform the actual validation.
     */
    private function performValidation(DownloadSession $session): array
    {
        $results = [
            'url_accessible' => false,
            'content_type_valid' => false,
            'file_size_matches' => false,
            'response_time' => null,
            'http_status' => null,
            'content_length' => null,
            'content_type' => null,
            'errors' => [],
        ];

        try {
            // Make HEAD request to check URL accessibility
            $startTime = microtime(true);

            $response = Http::timeout(30)
                ->withUserAgent('VideoDownloader/1.0 (Content Validator)')
                ->head($session->download_url);

            $results['response_time'] = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
            $results['http_status'] = $response->status();

            if ($response->successful()) {
                $results['url_accessible'] = true;

                // Check content type
                $contentType = $response->header('Content-Type');
                $results['content_type'] = $contentType;
                $results['content_type_valid'] = $this->isValidContentType($contentType, $session);

                // Check content length
                $contentLength = $response->header('Content-Length');
                if ($contentLength) {
                    $results['content_length'] = (int) $contentLength;
                    $results['file_size_matches'] = $this->isFileSizeMatch(
                        (int) $contentLength,
                        $session->file_size
                    );
                }

            } else {
                $results['errors'][] = "HTTP {$response->status()}: URL not accessible";
            }

        } catch (\Exception $e) {
            $results['errors'][] = 'Request failed: '.$e->getMessage();
        }

        return $results;
    }

    /**
     * Check if the content type is valid for the session format.
     */
    private function isValidContentType(?string $contentType, DownloadSession $session): bool
    {
        if (! $contentType) {
            return false;
        }

        $expectedTypes = match ($session->format->value) {
            'mp4' => ['video/mp4', 'video/x-mp4'],
            'webm' => ['video/webm'],
            'mp3' => ['audio/mpeg', 'audio/mp3'],
            default => [],
        };

        foreach ($expectedTypes as $expectedType) {
            if (str_contains(strtolower($contentType), $expectedType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the file size matches (within tolerance).
     */
    private function isFileSizeMatch(?int $actualSize, ?int $expectedSize): bool
    {
        if (! $actualSize || ! $expectedSize) {
            return false;
        }

        // Allow 10% tolerance
        $tolerance = 0.1;
        $difference = abs($actualSize - $expectedSize) / $expectedSize;

        return $difference <= $tolerance;
    }

    /**
     * Update session based on validation results.
     */
    private function updateSessionFromValidation(DownloadSession $session, array $results): void
    {
        $updateData = [];

        // Update file size if we got a more accurate value
        if ($results['content_length'] && ! $session->file_size) {
            $updateData['file_size'] = $results['content_length'];
        }

        // Mark as expired if URL is not accessible
        if (! $results['url_accessible']) {
            $updateData['status'] = DownloadSessionStatus::FAILED;
            $errorMessage = 'Download URL is no longer accessible';
            if (! empty($results['errors'])) {
                $errorMessage .= ': '.implode(', ', $results['errors']);
            }
            $updateData['error_message'] = $errorMessage;
        }

        // Add validation warnings to error message if there are issues
        $warnings = [];
        if (! $results['content_type_valid'] && $results['content_type']) {
            $warnings[] = "Unexpected content type: {$results['content_type']}";
        }
        if (! $results['file_size_matches'] && $results['content_length']) {
            $warnings[] = "File size mismatch: expected {$session->file_size}, got {$results['content_length']}";
        }

        if (! empty($warnings)) {
            $currentError = $session->error_message ?? '';
            $warningText = 'Validation warnings: '.implode(', ', $warnings);
            $updateData['error_message'] = $currentError ? $currentError.' | '.$warningText : $warningText;
        }

        if (! empty($updateData)) {
            $session->update($updateData);
        }
    }
}
