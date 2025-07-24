<?php

namespace App\Services\VideoExtraction\Drivers;

use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\Contracts\DriverInterface;
use App\Services\VideoExtraction\Contracts\ExtractorInterface;
use App\Services\VideoExtraction\DTOs\ExtractionResult;
use App\Services\VideoExtraction\Exceptions\ExtractionFailedException;
use App\Services\VideoExtraction\Exceptions\InvalidUrlException;
use App\Services\VideoExtraction\Exceptions\RateLimitExceededException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Abstract base class for video extraction drivers.
 * 
 * This class provides common functionality that all platform drivers
 * can inherit, including URL validation, logging, and error handling.
 */
abstract class AbstractDriver implements DriverInterface, ExtractorInterface
{
    /**
     * The platform this driver handles.
     */
    protected Platform $platform;

    /**
     * The supported video qualities for this platform.
     *
     * @var array<VideoQuality>
     */
    protected array $supportedQualities = [];

    /**
     * The supported video formats for this platform.
     *
     * @var array<VideoFormat>
     */
    protected array $supportedFormats = [];

    /**
     * URL patterns this driver can handle.
     *
     * @var array<string>
     */
    protected array $urlPatterns = [];

    /**
     * Driver configuration options.
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * Create a new driver instance.
     *
     * @param array<string, mixed> $config Driver configuration
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->initialize();
    }

    /**
     * Initialize the driver with platform-specific settings.
     */
    abstract protected function initialize(): void;

    /**
     * Get the default configuration for this driver.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            'timeout' => config('video-extraction.yt_dlp.timeout', 300),
            'download_timeout' => config('video-extraction.yt_dlp.download_timeout', 600),
            'max_retries' => config('video-extraction.yt_dlp.max_retries', 3),
            'retry_delay' => 1000, // milliseconds
            'user_agent' => config('video-extraction.yt_dlp.user_agent', 'Mozilla/5.0 (compatible; VideoDownloader/1.0)'),
            'yt_dlp_binary' => config('video-extraction.yt_dlp.binary_path', '/usr/local/bin/yt-dlp'),
            'temp_dir' => config('video-extraction.temp.directory', storage_path('app/temp/video-extraction')),
            'temp_download_path' => config('video-extraction.local_download.temp_path', 'temp-downloads'),
            'cleanup_on_success' => config('video-extraction.local_download.cleanup_on_success', true),
            'cleanup_on_error' => config('video-extraction.local_download.cleanup_on_error', true),
        ];
    }

    /**
     * Extract video metadata from the given URL.
     */
    public function extractMetadata(string $url, array $options = []): ExtractionResult
    {
        $this->validateUrl($url);
        
        Log::info("Extracting metadata from {$this->platform->value}", [
            'url' => $url,
            'driver' => static::class,
        ]);

        try {
            return $this->performExtraction($url, $options);
        } catch (\Exception $e) {
            Log::error("Metadata extraction failed for {$this->platform->value}", [
                'url' => $url,
                'error' => $e->getMessage(),
                'driver' => static::class,
            ]);

            throw new ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Perform the actual extraction logic.
     * This method should be implemented by each platform driver.
     *
     * @param string $url The URL to extract from
     * @param array $options Extraction options
     * @return ExtractionResult The extraction result
     */
    abstract protected function performExtraction(string $url, array $options = []): ExtractionResult;

    /**
     * Perform extraction with local download, R2 upload, and cleanup workflow.
     * This method implements the new CORS workaround solution.
     *
     * @param string $url The URL to extract from
     * @param array $options Extraction options
     * @return ExtractionResult The extraction result with R2 URL
     * @throws ExtractionFailedException
     */
    protected function performExtractionWithDownload(string $url, array $options = []): ExtractionResult
    {
        $localPath = null;

        try {
            // Phase 1: Extract metadata using existing yt-dlp functionality
            Log::info('Starting video extraction with download workflow', [
                'url' => $url,
                'platform' => $this->platform->value,
                'options' => $options,
            ]);

            $ytDlpData = $this->executeYtDlp($url, $options);

            // Phase 2: Download video locally
            $localPath = $this->downloadVideoLocally($url, $options);

            // Phase 3: Upload to R2 storage
            $videoId = $ytDlpData['id'] ?? $this->extractVideoId($url);
            if (!$videoId) {
                throw new ExtractionFailedException(
                    url: $url,
                    platform: $this->platform,
                    reason: 'Could not determine video ID for R2 upload'
                );
            }

            $r2Url = $this->uploadToR2Storage($localPath, $videoId);

            // Phase 4: Create extraction result with R2 URL
            $extractionResult = $this->createExtractionResultFromYtDlp($ytDlpData, $options);
            $extractionResult->setDownloadUrl($r2Url);

            // Phase 5: Cleanup local file
            $this->cleanupLocalFile($localPath);

            Log::info('Video extraction with download completed successfully', [
                'url' => $url,
                'platform' => $this->platform->value,
                'r2_url' => $r2Url,
                'video_id' => $videoId,
            ]);

            return $extractionResult;

        } catch (\Exception $e) {
            // Cleanup on any error
            if ($localPath && file_exists($localPath)) {
                $this->cleanupLocalFile($localPath);
            }

            Log::error('Video extraction with download failed', [
                'url' => $url,
                'platform' => $this->platform->value,
                'error' => $e->getMessage(),
                'local_path' => $localPath,
            ]);

            // Re-throw the exception to maintain error handling behavior
            throw $e;
        }
    }

    /**
     * Create extraction result from yt-dlp data.
     * This method should be implemented by each driver.
     *
     * @param array $ytDlpData
     * @param array $options
     * @return ExtractionResult
     */
    abstract protected function createExtractionResultFromYtDlp(array $ytDlpData, array $options = []): ExtractionResult;

    /**
     * Get the download URL for the video with specified quality and format.
     */
    public function getDownloadUrl(string $url, VideoQuality $quality, VideoFormat $format): string
    {
        $this->validateUrl($url);
        $this->validateQuality($quality);
        $this->validateFormat($format);

        $result = $this->extractMetadata($url, [
            'quality' => $quality,
            'format' => $format,
        ]);

        $downloadUrl = $result->getDownloadUrl();
        
        if (!$downloadUrl) {
            throw new ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'No download URL found in extraction result'
            );
        }

        return $downloadUrl;
    }

    /**
     * Validate if the given URL is valid for this platform.
     */
    public function validateUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidUrlException($url, 'Invalid URL format');
        }

        if (!$this->supports($url)) {
            throw new InvalidUrlException(
                $url, 
                "URL is not supported by {$this->platform->value} driver"
            );
        }

        return true;
    }

    /**
     * Check if this driver supports the given URL.
     */
    public function supports(string $url): bool
    {
        foreach ($this->urlPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the platform this driver handles.
     */
    public function getPlatform(): Platform
    {
        return $this->platform;
    }

    /**
     * Get the supported video qualities for this platform.
     */
    public function getSupportedQualities(): array
    {
        return $this->supportedQualities;
    }

    /**
     * Get the supported video formats for this platform.
     */
    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Get the URL patterns this driver can handle.
     */
    public function getUrlPatterns(): array
    {
        return $this->urlPatterns;
    }

    /**
     * Extract video content and metadata from the given URL.
     */
    public function extract(string $url, array $options = []): ExtractionResult
    {
        return $this->extractMetadata($url, $options);
    }

    /**
     * Get the priority of this extractor.
     */
    public function getPriority(): int
    {
        return 100; // Default priority
    }

    /**
     * Get the name of this extractor.
     */
    public function getName(): string
    {
        return $this->platform->value . '_driver';
    }

    /**
     * Get the version of this extractor.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Validate that the quality is supported by this driver.
     *
     * @param VideoQuality $quality
     * @throws ExtractionFailedException
     */
    protected function validateQuality(VideoQuality $quality): void
    {
        if (!in_array($quality, $this->supportedQualities)) {
            throw new ExtractionFailedException(
                url: '',
                platform: $this->platform,
                reason: "Quality {$quality->value} is not supported by {$this->platform->value}"
            );
        }
    }

    /**
     * Validate that the format is supported by this driver.
     *
     * @param VideoFormat $format
     * @throws ExtractionFailedException
     */
    protected function validateFormat(VideoFormat $format): void
    {
        if (!in_array($format, $this->supportedFormats)) {
            throw new ExtractionFailedException(
                url: '',
                platform: $this->platform,
                reason: "Format {$format->value} is not supported by {$this->platform->value}"
            );
        }
    }

    /**
     * Execute yt-dlp command with retry logic.
     *
     * @param string $url
     * @param array $options
     * @return array
     * @throws RateLimitExceededException
     * @throws ExtractionFailedException
     */
    protected function executeYtDlp(string $url, array $options = []): array
    {
        $maxRetries = $this->config['max_retries'];
        $retryDelay = $this->config['retry_delay'];

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $command = $this->buildYtDlpCommand($url, $options);

                Log::debug('Executing yt-dlp command', [
                    'command' => $command,
                    'attempt' => $attempt,
                    'platform' => $this->platform->value,
                ]);

                $result = Process::timeout($this->config['timeout'])
                    ->run($command);

                if ($result->successful()) {
                    $output = $result->output();
                    return $this->parseYtDlpOutput($output);
                }

                $errorOutput = $result->errorOutput();

                // Check for rate limiting
                if (str_contains($errorOutput, 'rate limit') || str_contains($errorOutput, '429')) {
                    throw new RateLimitExceededException(
                        platform: $this->platform,
                        retryAfter: 60, // Default retry after 1 minute
                        reason: 'yt-dlp rate limited'
                    );
                }

                if ($attempt === $maxRetries) {
                    throw new ExtractionFailedException(
                        url: $url,
                        platform: $this->platform,
                        reason: "yt-dlp failed: {$errorOutput}"
                    );
                }

            } catch (RateLimitExceededException $e) {
                throw $e; // Re-throw rate limit exceptions immediately
            } catch (\Exception $e) {
                if ($attempt === $maxRetries) {
                    throw new ExtractionFailedException(
                        url: $url,
                        platform: $this->platform,
                        reason: $e->getMessage(),
                        previous: $e
                    );
                }
            }

            // Wait before retrying
            usleep($retryDelay * 1000);
        }

        throw new ExtractionFailedException(
            url: $url,
            platform: $this->platform,
            reason: 'Max retries exceeded'
        );
    }

    /**
     * Extract video ID from URL using platform-specific logic.
     *
     * @param string $url
     * @return string|null
     */
    abstract protected function extractVideoId(string $url): ?string;

    /**
     * Build yt-dlp command for extraction.
     *
     * @param string $url
     * @param array $options
     * @return string
     */
    protected function buildYtDlpCommand(string $url, array $options = []): string
    {
        $binary = $this->config['yt_dlp_binary'];
        $command = [$binary];

        // Add basic options
        $command[] = '--dump-json';
        $command[] = '--no-warnings';
        $command[] = '--user-agent';
        $command[] = escapeshellarg($this->config['user_agent']);

        // Add quality/format options
        if (isset($options['quality']) && isset($options['format'])) {
            $formatString = $this->buildFormatString($options['quality'], $options['format']);
            if ($formatString) {
                $command[] = '--format';
                $command[] = escapeshellarg($formatString);
            }
        }

        // Add platform-specific options
        $platformOptions = $this->getPlatformYtDlpOptions();
        foreach ($platformOptions as $option => $value) {
            if (is_bool($value)) {
                if ($value) {
                    $command[] = $option;
                }
            } else {
                $command[] = $option;
                if ($value !== null) {
                    $command[] = escapeshellarg($value);
                }
            }
        }

        // Add the URL
        $command[] = escapeshellarg($url);

        return implode(' ', $command);
    }

    /**
     * Parse yt-dlp JSON output.
     *
     * @param string $output
     * @return array
     */
    protected function parseYtDlpOutput(string $output): array
    {
        $lines = explode("\n", trim($output));
        $jsonData = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $decoded = json_decode($line, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $jsonData = $decoded;
                break; // Use the first valid JSON object
            }
        }

        if (empty($jsonData)) {
            throw new ExtractionFailedException(
                url: '',
                platform: $this->platform,
                reason: 'Failed to parse yt-dlp output'
            );
        }

        return $jsonData;
    }

    /**
     * Build format string for yt-dlp based on quality and format.
     *
     * @param VideoQuality $quality
     * @param VideoFormat $format
     * @return string|null
     */
    protected function buildFormatString(VideoQuality $quality, VideoFormat $format): ?string
    {
        $qualityMappings = config('video-extraction.quality.mappings', []);
        $formatMappings = config('video-extraction.format.mappings', []);

        if ($format === VideoFormat::MP3) {
            return $formatMappings['mp3'] ?? 'bestaudio[ext=m4a]/bestaudio/best';
        }

        $qualityString = $qualityMappings[$quality->value] ?? 'best';
        $formatString = $formatMappings[$format->value] ?? $format->value;

        return "{$qualityString}[ext={$formatString}]/{$qualityString}";
    }

    /**
     * Get platform-specific yt-dlp options.
     *
     * @return array
     */
    protected function getPlatformYtDlpOptions(): array
    {
        $platformConfig = config("video-extraction.drivers.{$this->platform->value}", []);
        return $platformConfig['yt_dlp_options'] ?? [];
    }

    /**
     * Generate standardized local download path for video files.
     *
     * @param string $videoId
     * @param VideoFormat $format
     * @return string
     */
    protected function generateLocalDownloadPath(string $videoId, VideoFormat $format): string
    {
        $timestamp = time();
        $platform = strtolower($this->platform->value);
        $extension = $format->value;

        // Sanitize video ID for file system
        $sanitizedVideoId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $videoId);

        $directory = storage_path("app/temp-downloads/{$platform}/{$sanitizedVideoId}");
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return "{$directory}/{$timestamp}.{$extension}";
    }

    /**
     * Download video locally using yt-dlp.
     *
     * @param string $url
     * @param array $options
     * @return string Local file path
     * @throws ExtractionFailedException
     */
    protected function downloadVideoLocally(string $url, array $options): string
    {
        $videoId = $this->extractVideoId($url);
        if (!$videoId) {
            throw new ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Could not extract video ID for local download'
            );
        }

        $format = $options['format'] ?? VideoFormat::MP4;
        $localPath = $this->generateLocalDownloadPath($videoId, $format);

        $command = $this->buildYtDlpDownloadCommand($url, $localPath, $options);

        Log::info('Downloading video locally', [
            'url' => $url,
            'platform' => $this->platform->value,
            'local_path' => $localPath,
            'command' => $command,
        ]);

        $downloadTimeout = $this->config['download_timeout'] ?? 300;
        $result = Process::timeout($downloadTimeout)->run($command);

        if (!$result->successful()) {
            // Cleanup partial download if exists
            if (file_exists($localPath)) {
                unlink($localPath);
            }

            throw new ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Failed to download video locally: ' . $result->errorOutput()
            );
        }

        // Verify file was actually downloaded
        if (!file_exists($localPath) || filesize($localPath) === 0) {
            throw new ExtractionFailedException(
                url: $url,
                platform: $this->platform,
                reason: 'Downloaded file is missing or empty'
            );
        }

        Log::info('Video downloaded successfully', [
            'url' => $url,
            'platform' => $this->platform->value,
            'local_path' => $localPath,
            'file_size' => filesize($localPath),
        ]);

        return $localPath;
    }

    /**
     * Build yt-dlp command for downloading video files.
     *
     * @param string $url
     * @param string $outputPath
     * @param array $options
     * @return string
     */
    protected function buildYtDlpDownloadCommand(string $url, string $outputPath, array $options): string
    {
        $binary = $this->config['yt_dlp_binary'];
        $command = [$binary];

        // Use best quality format
        $command[] = '-f';
        $command[] = 'best';

        // Set output path
        $command[] = '-o';
        $command[] = escapeshellarg($outputPath);

        // Add user agent
        $command[] = '--user-agent';
        $command[] = escapeshellarg($this->config['user_agent']);

        // Add platform-specific options for download
        $platformOptions = $this->getPlatformYtDlpOptions();
        foreach ($platformOptions as $option => $value) {
            // Skip metadata-only options for download
            if (in_array($option, ['--dump-json', '--write-info-json'])) {
                continue;
            }

            if (is_bool($value)) {
                if ($value) {
                    $command[] = $option;
                }
            } else {
                $command[] = $option;
                if ($value !== null) {
                    $command[] = escapeshellarg($value);
                }
            }
        }

        // Add the URL
        $command[] = escapeshellarg($url);

        return implode(' ', $command);
    }

    /**
     * Upload downloaded video file to R2 storage with performance optimizations.
     *
     * This method implements streaming upload for better memory efficiency and
     * multipart upload for large files to improve upload speed and reliability.
     *
     * @param string $localPath
     * @param string $videoId
     * @return string R2 URL
     * @throws ExtractionFailedException
     */
    protected function uploadToR2Storage(string $localPath, string $videoId): string
    {
        if (!file_exists($localPath)) {
            throw new ExtractionFailedException(
                url: '',
                platform: $this->platform,
                reason: 'Local file not found for R2 upload: ' . $localPath
            );
        }

        $platform = strtolower($this->platform->value);
        $filename = basename($localPath);
        $sanitizedVideoId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $videoId);
        $r2Path = "videos/{$platform}/{$sanitizedVideoId}/{$filename}";
        $fileSize = filesize($localPath);

        Log::info('Uploading video to R2 storage', [
            'local_path' => $localPath,
            'r2_path' => $r2Path,
            'platform' => $this->platform->value,
            'file_size' => $fileSize,
            'upload_method' => $this->getOptimalUploadMethod($fileSize),
        ]);

        try {
            // Use optimized upload method based on file size
            $uploaded = $this->performOptimizedUpload($localPath, $r2Path, $fileSize);

            if (!$uploaded) {
                throw new ExtractionFailedException(
                    url: '',
                    platform: $this->platform,
                    reason: 'Failed to upload file to R2 storage'
                );
            }

            $r2Url = Storage::disk('r2')->url($r2Path);

            Log::info('Video uploaded to R2 successfully', [
                'r2_path' => $r2Path,
                'r2_url' => $r2Url,
                'platform' => $this->platform->value,
                'file_size' => $fileSize,
            ]);

            return $r2Url;

        } catch (\Exception $e) {
            throw new ExtractionFailedException(
                url: '',
                platform: $this->platform,
                reason: 'R2 upload failed: ' . $e->getMessage()
            );
        }
    }

    /**
     * Clean up local downloaded file and empty directories.
     *
     * @param string $localPath
     * @return void
     */
    protected function cleanupLocalFile(string $localPath): void
    {
        if (file_exists($localPath)) {
            unlink($localPath);

            Log::debug('Local file cleaned up', [
                'path' => $localPath,
                'platform' => $this->platform->value,
            ]);

            // Cleanup empty directories
            $directory = dirname($localPath);
            if (is_dir($directory) && $this->isDirectoryEmpty($directory)) {
                rmdir($directory);

                // Cleanup parent directory if empty too
                $parentDir = dirname($directory);
                if (is_dir($parentDir) && $this->isDirectoryEmpty($parentDir)) {
                    rmdir($parentDir);
                }
            }
        }
    }

    /**
     * Determine the optimal upload method based on file size.
     *
     * @param int $fileSize File size in bytes
     * @return string Upload method ('streaming', 'multipart', or 'standard')
     */
    private function getOptimalUploadMethod(int $fileSize): string
    {
        // Use multipart upload for files larger than 100MB
        if ($fileSize > 100 * 1024 * 1024) {
            return 'multipart';
        }

        // Use streaming upload for files larger than 10MB
        if ($fileSize > 10 * 1024 * 1024) {
            return 'streaming';
        }

        // Use standard upload for smaller files
        return 'standard';
    }

    /**
     * Perform optimized upload based on file size and available methods.
     *
     * @param string $localPath Local file path
     * @param string $r2Path R2 storage path
     * @param int $fileSize File size in bytes
     * @return bool Upload success status
     */
    private function performOptimizedUpload(string $localPath, string $r2Path, int $fileSize): bool
    {
        $uploadMethod = $this->getOptimalUploadMethod($fileSize);

        return match ($uploadMethod) {
            'multipart' => $this->uploadWithMultipart($localPath, $r2Path, $fileSize),
            'streaming' => $this->uploadWithStreaming($localPath, $r2Path),
            'standard' => Storage::disk('r2')->put($r2Path, file_get_contents($localPath)),
        };
    }

    /**
     * Upload file using streaming to reduce memory usage.
     *
     * @param string $localPath Local file path
     * @param string $r2Path R2 storage path
     * @return bool Upload success status
     */
    private function uploadWithStreaming(string $localPath, string $r2Path): bool
    {
        try {
            $stream = fopen($localPath, 'r');
            if (!$stream) {
                return false;
            }

            $result = Storage::disk('r2')->put($r2Path, $stream);
            fclose($stream);

            return $result;
        } catch (\Exception $e) {
            Log::warning('Streaming upload failed, falling back to standard upload', [
                'error' => $e->getMessage(),
                'local_path' => $localPath,
                'r2_path' => $r2Path,
            ]);

            // Fallback to standard upload
            return Storage::disk('r2')->put($r2Path, file_get_contents($localPath));
        }
    }

    /**
     * Upload large file using multipart upload for better performance.
     *
     * @param string $localPath Local file path
     * @param string $r2Path R2 storage path
     * @param int $fileSize File size in bytes
     * @return bool Upload success status
     */
    private function uploadWithMultipart(string $localPath, string $r2Path, int $fileSize): bool
    {
        try {
            // For now, use streaming upload as Laravel's Storage facade doesn't
            // directly support multipart uploads. This could be enhanced with
            // direct AWS SDK usage for true multipart uploads.
            Log::info('Using streaming upload for large file (multipart not yet implemented)', [
                'file_size' => $fileSize,
                'local_path' => $localPath,
                'r2_path' => $r2Path,
            ]);

            return $this->uploadWithStreaming($localPath, $r2Path);
        } catch (\Exception $e) {
            Log::warning('Multipart upload failed, falling back to standard upload', [
                'error' => $e->getMessage(),
                'local_path' => $localPath,
                'r2_path' => $r2Path,
            ]);

            // Fallback to standard upload
            return Storage::disk('r2')->put($r2Path, file_get_contents($localPath));
        }
    }

    /**
     * Check if directory is empty (contains only . and ..).
     *
     * @param string $directory
     * @return bool
     */
    private function isDirectoryEmpty(string $directory): bool
    {
        $files = scandir($directory);
        return count($files) <= 2; // Only . and ..
    }
}
