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
            'max_retries' => config('video-extraction.yt_dlp.max_retries', 3),
            'retry_delay' => 1000, // milliseconds
            'user_agent' => config('video-extraction.yt_dlp.user_agent', 'Mozilla/5.0 (compatible; VideoDownloader/1.0)'),
            'yt_dlp_binary' => config('video-extraction.yt_dlp.binary_path', '/usr/local/bin/yt-dlp'),
            'temp_dir' => config('video-extraction.temp.directory', storage_path('app/temp/video-extraction')),
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
}
