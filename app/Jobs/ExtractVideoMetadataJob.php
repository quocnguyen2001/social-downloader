<?php

namespace App\Jobs;

use App\Models\DownloadOption;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\Exceptions\YtDlpException;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job to extract video metadata using yt-dlp and create DownloadOption records.
 */
class ExtractVideoMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $downloadSessionId,
        private array $options = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(YtDlpService $ytDlpService): void
    {
        $startTime = microtime(true);

        try {
            $downloadSession = $this->getDownloadSession();

            Log::info('Starting video metadata extraction', [
                'download_session_id' => $this->downloadSessionId,
                'url' => $downloadSession->original_url,
                'platform' => $downloadSession->platform->value,
            ]);

            // Update status to fetching metadata
            $downloadSession->markAsFetchingMetadata();

            // Get available formats using yt-dlp
            $formats = $ytDlpService->getAvailableFormats($downloadSession->original_url);

            if (empty($formats)) {
                throw new YtDlpException('No video formats found for the provided URL');
            }

            Log::info('Retrieved video formats', [
                'download_session_id' => $this->downloadSessionId,
                'format_count' => count($formats),
            ]);

            // Extract and populate video metadata
            $this->populateVideoMetadata($downloadSession, $ytDlpService);

            // Create DownloadOption records for each format
            $this->createDownloadOptions($downloadSession, $formats);

            // Update session status to metadata fetched
            $downloadSession->markAsMetadataFetched();

            $processingTime = microtime(true) - $startTime;

            Log::info('Video metadata extraction completed', [
                'download_session_id' => $this->downloadSessionId,
                'processing_time' => $processingTime,
                'formats_created' => count($formats),
            ]);

        } catch (YtDlpException $e) {
            $this->handleYtDlpError($e);
        } catch (\Exception $e) {
            $this->handleGeneralError($e);
        }
    }

    /**
     * Handle the job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Video metadata extraction job failed permanently', [
            'download_session_id' => $this->downloadSessionId,
            'exception' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed('Metadata extraction failed: '.$exception->getMessage());
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
     * Populate DownloadSession with video metadata.
     */
    private function populateVideoMetadata(DownloadSession $downloadSession, YtDlpService $ytDlpService): void
    {
        try {
            Log::info('Extracting video metadata', [
                'download_session_id' => $downloadSession->id,
                'url' => $downloadSession->original_url,
            ]);

            $metadata = $ytDlpService->getVideoMetadata($downloadSession->original_url);

            if (! empty($metadata)) {
                // Filter out null values to avoid overwriting existing data with nulls
                $updateData = array_filter($metadata, fn ($value) => $value !== null);

                if (! empty($updateData)) {
                    $downloadSession->update($updateData);

                    Log::info('Video metadata populated successfully', [
                        'download_session_id' => $downloadSession->id,
                        'updated_fields' => array_keys($updateData),
                        'video_id' => $metadata['video_id'] ?? 'not extracted',
                        'title' => isset($metadata['title']) ? substr($metadata['title'], 0, 50).'...' : 'not extracted',
                    ]);
                } else {
                    Log::warning('No valid metadata extracted', [
                        'download_session_id' => $downloadSession->id,
                    ]);
                }
            } else {
                Log::warning('Failed to extract video metadata', [
                    'download_session_id' => $downloadSession->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error populating video metadata', [
                'download_session_id' => $downloadSession->id,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the job if metadata extraction fails
        }
    }

    /**
     * Create DownloadOption records for each format with deduplication.
     * Implements unique constraint on (download_session_id, quality) and selects
     * the format with the smallest filesize for each quality.
     */
    private function createDownloadOptions(DownloadSession $downloadSession, array $formats): void
    {
        DB::transaction(function () use ($downloadSession, $formats) {
            // Clear any existing download options for this session
            $downloadSession->downloadOptions()->delete();

            // Filter suitable formats and apply quality filtering
            $suitableFormats = collect($formats)
                ->filter(fn ($format) => $format->isSuitableForDownload());

            // Apply quality filtering to only allow standard qualities
            $filteredFormats = $this->filterAllowedQualities($suitableFormats);

            // Group formats by their standardized quality value
            $formatsByQuality = $filteredFormats->groupBy(function ($format) {
                return $this->getStandardizedQuality($format);
            });

            Log::info('Filtered and grouped formats by quality', [
                'download_session_id' => $downloadSession->id,
                'total_formats' => $suitableFormats->count(),
                'filtered_formats' => $filteredFormats->count(),
                'quality_groups' => $formatsByQuality->keys()->toArray(),
                'group_counts' => $formatsByQuality->map->count()->toArray(),
            ]);

            // For each quality group, select the best format (smallest filesize)
            foreach ($formatsByQuality as $quality => $qualityFormats) {
                $bestFormat = $this->selectBestFormat($qualityFormats);

                if (! $bestFormat) {
                    Log::warning('No suitable format found for quality', [
                        'download_session_id' => $downloadSession->id,
                        'quality' => $quality,
                    ]);

                    continue;
                }

                // Get the base data from format mapping
                $downloadOptionData = $bestFormat->toArray();

                // Add required fields
                $downloadOptionData['download_session_id'] = $downloadSession->id;

                // Set estimated download time based on file size
                $downloadOptionData['estimated_download_time'] = $this->estimateDownloadTime($bestFormat->filesize);

                Log::debug('Creating download option', [
                    'download_session_id' => $downloadSession->id,
                    'quality' => $quality,
                    'format_id' => $bestFormat->formatId,
                    'filesize' => $bestFormat->filesize,
                    'selected_from_count' => $qualityFormats->count(),
                ]);

                DownloadOption::query()->create($downloadOptionData);
            }
        });

        Log::info('Created deduplicated download options', [
            'download_session_id' => $downloadSession->id,
            'options_created' => $downloadSession->downloadOptions()->count(),
        ]);
    }

    /**
     * Get standardized quality value from VideoFormat.
     * This mirrors the logic in VideoFormat::getStandardizedQuality().
     */
    private function getStandardizedQuality($format): string
    {
        // Handle audio-only formats
        if ($format->isAudioOnly || $format->resolution === 'audio only') {
            return 'audio';
        }

        // Extract height from resolution like "1920x1080"
        if (preg_match('/(\d+)x(\d+)/', $format->resolution, $matches)) {
            return $matches[2]; // Return height as string
        }

        // Handle direct quality labels like "720p"
        if (preg_match('/(\d+)p/', $format->resolution, $matches)) {
            return $matches[1];
        }

        // Use qualityLabel if available and extract number
        if ($format->qualityLabel && preg_match('/(\d+)p/', $format->qualityLabel, $matches)) {
            return $matches[1];
        }

        // Extract any number from resolution as fallback
        if (preg_match('/(\d+)/', $format->resolution, $matches)) {
            return $matches[1];
        }

        // Final fallback to original resolution
        return $format->resolution;
    }

    /**
     * Filter formats to only include allowed quality values.
     *
     * @param  \Illuminate\Support\Collection  $formats
     * @return \Illuminate\Support\Collection
     */
    private function filterAllowedQualities($formats)
    {
        $allowedQualities = ['audio', '144', '360', '720', '1080'];

        $originalCount = $formats->count();

        $filteredFormats = $formats->filter(function ($format) use ($allowedQualities) {
            $quality = $this->getStandardizedQuality($format);

            return in_array($quality, $allowedQualities);
        });

        $filteredCount = $filteredFormats->count();
        $removedCount = $originalCount - $filteredCount;

        if ($removedCount > 0) {
            Log::info('Quality filtering applied', [
                'original_count' => $originalCount,
                'filtered_count' => $filteredCount,
                'removed_count' => $removedCount,
                'allowed_qualities' => $allowedQualities,
            ]);
        }

        // Ensure we have at least some formats remaining
        if ($filteredFormats->isEmpty()) {
            Log::warning('Quality filtering removed all formats, falling back to original formats', [
                'original_count' => $originalCount,
                'allowed_qualities' => $allowedQualities,
            ]);

            return $formats; // Fallback to original formats to avoid empty result
        }

        return $filteredFormats;
    }

    /**
     * Select the best format from a collection of formats with the same quality.
     * Prioritizes formats with the smallest filesize.
     */
    private function selectBestFormat($qualityFormats)
    {
        // First, try to find formats with valid filesize and select the smallest
        $formatsWithSize = $qualityFormats->filter(fn ($format) => $format->filesize !== null && $format->filesize > 0);

        if ($formatsWithSize->isNotEmpty()) {
            return $formatsWithSize->sortBy('filesize')->first();
        }

        // If no formats have filesize, just return the first one
        return $qualityFormats->first();
    }

    /**
     * Estimate download time based on file size.
     */
    private function estimateDownloadTime(?int $fileSize): ?int
    {
        if (! $fileSize) {
            return null;
        }

        // Assume average download speed of 1 MB/s (conservative estimate)
        $averageSpeedBytesPerSecond = 1024 * 1024; // 1 MB/s

        return (int) ceil($fileSize / $averageSpeedBytesPerSecond);
    }

    /**
     * Handle yt-dlp specific errors.
     */
    private function handleYtDlpError(YtDlpException $e): void
    {
        Log::error('yt-dlp error during metadata extraction', [
            'download_session_id' => $this->downloadSessionId,
            'error' => $e->getMessage(),
            'exit_code' => $e->getExitCode(),
            'is_unsupported_url' => $e->isUnsupportedUrl(),
            'is_network_error' => $e->isNetworkError(),
            'is_video_unavailable' => $e->isVideoUnavailable(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed($e->getUserFriendlyMessage());
        } catch (\Exception $updateException) {
            Log::error('Failed to update session after yt-dlp error', [
                'download_session_id' => $this->downloadSessionId,
                'update_error' => $updateException->getMessage(),
            ]);
        }

        // Don't retry for certain types of errors
        if ($e->isUnsupportedUrl() || $e->isVideoUnavailable()) {
            $this->fail($e);
        } else {
            throw $e; // Allow retry for network errors
        }
    }

    /**
     * Handle general errors.
     */
    private function handleGeneralError(\Exception $e): void
    {
        Log::error('General error during metadata extraction', [
            'download_session_id' => $this->downloadSessionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        try {
            $downloadSession = $this->getDownloadSession();
            $downloadSession->markAsFailed('Extraction failed: '.$e->getMessage());
        } catch (\Exception $updateException) {
            Log::error('Failed to update session after general error', [
                'download_session_id' => $this->downloadSessionId,
                'update_error' => $updateException->getMessage(),
            ]);
        }

        throw $e; // Allow retry
    }
}
