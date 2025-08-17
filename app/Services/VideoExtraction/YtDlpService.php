<?php

namespace App\Services\VideoExtraction;

use App\Models\DownloadOption;
use App\Services\ThumbnailService;
use App\Services\VideoExtraction\DTOs\VideoFormat;
use App\Services\VideoExtraction\Exceptions\YtDlpException;
use App\Settings\CookieSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Service for executing yt-dlp commands and parsing video format information.
 */
class YtDlpService
{
    protected FilenameService $filenameService;

    protected ThumbnailService $thumbnailService;

    public function __construct(FilenameService $filenameService, ThumbnailService $thumbnailService)
    {
        $this->filenameService = $filenameService;
        $this->thumbnailService = $thumbnailService;
        $this->validateBinary();
    }

    /**
     * Validate that yt-dlp binary is available.
     */
    private function validateBinary(): void
    {
        $binaryPath = config('video-extraction.yt_dlp.binary_path', 'yt-dlp');

        try {
            // Try to get version to validate binary works
            $result = Process::timeout(10)->run([$binaryPath, '--version']);

            if ($result->successful()) {
                Log::info('yt-dlp binary validated successfully', [
                    'binary_path' => $binaryPath,
                    'version' => trim($result->output()),
                ]);
            } else {
                Log::warning('yt-dlp binary validation failed', [
                    'binary_path' => $binaryPath,
                    'error' => $result->errorOutput(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to validate yt-dlp binary', [
                'binary_path' => $binaryPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve cookie file path with fallback mechanism.
     *
     * Priority:
     * 1. Environment variable YT_DLP_COOKIES_FILE_PATH (if set and not empty)
     * 2. Settings form data (CookieSettings::cookie_file_path)
     *
     * @return string|null The resolved cookie file path or null if none available
     */
    private function resolveCookieFilePath(): ?string
    {
        // Check raw environment variable (without default)
        $envPath = config('video-extraction.yt_dlp.cookies_file_path');

        // If environment variable is explicitly set and not empty, use it
        if ($envPath !== null && trim($envPath) !== '') {
            Log::info('Using cookie file path from environment variable', [
                'path' => $envPath,
                'source' => 'environment',
            ]);

            return $envPath;
        }

        // Fall back to settings
        try {
            $cookieSettings = app(CookieSettings::class);
            $settingsPath = $cookieSettings->cookie_file_path;

            if ($settingsPath !== null && trim($settingsPath) !== '') {
                Log::info('Using cookie file path from settings', [
                    'path' => $settingsPath,
                    'source' => 'settings',
                ]);

                return $settingsPath;
            }

            Log::info('No cookie file path configured in settings');

            return null;

        } catch (\Exception $e) {
            Log::warning('Failed to load cookie settings, no fallback available', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Build yt-dlp command with proper configuration and platform-specific options.
     *
     * @param  string  $url  The video URL
     * @param  array  $additionalOptions  Additional yt-dlp options
     * @return array Command array for Process::run()
     */
    private function buildYtDlpCommand(string $url, array $additionalOptions = []): array
    {
        // Get binary path from configuration
        $binaryPath = config('video-extraction.yt_dlp.binary_path', 'yt-dlp');

        // Validate binary exists
        if ($binaryPath !== 'yt-dlp' && ! file_exists($binaryPath)) {
            Log::warning('yt-dlp binary not found at configured path', [
                'configured_path' => $binaryPath,
                'falling_back_to' => 'yt-dlp',
            ]);
            $binaryPath = 'yt-dlp';
        }

        // Start building command
        $command = [$binaryPath];

        // Add cookie support first (authentication must come before format selection)
        if (config('video-extraction.yt_dlp.using_cookies', false)) {
            $cookieFilePath = $this->resolveCookieFilePath();

            if ($cookieFilePath) {
                $resolvedCookiePath =
                    (File::exists(base_path($cookieFilePath)) ? base_path($cookieFilePath) : Storage::disk('local')->exists($cookieFilePath)) ? Storage::disk('local')->path($cookieFilePath) : null;

                if (file_exists($resolvedCookiePath)) {
                    $command[] = '--cookies';
                    $command[] = $resolvedCookiePath;

                    Log::info('Using cookies for yt-dlp command', [
                        'cookie_file' => $resolvedCookiePath,
                        'url' => $url,
                    ]);
                } else {
                    Log::warning('Cookie file not found, proceeding without cookies', [
                        'configured_path' => $cookieFilePath,
                        'resolved_path' => $resolvedCookiePath,
                        'url' => $url,
                    ]);
                }
            } else {
                Log::info('No cookie file path configured, proceeding without cookies', [
                    'url' => $url,
                ]);
            }
        }

        // Add standard options
        if (config('video-extraction.yt_dlp.no_warnings', true)) {
            $command[] = '--no-warnings';
        }

        $command[] = '--no-playlist';

        // Add additional options (including format selection) after authentication
        $command = array_merge($command, $additionalOptions);

        // Add platform-specific options (includes user-agent for Instagram)
        $platformOptions = $this->getPlatformSpecificOptions($url);
        if (! empty($platformOptions)) {
            $command = array_merge($command, $platformOptions);
        }

        // Add Instagram-specific options to avoid blocking (only if not already added)
        if ($this->isInstagramUrl($url)) {
            // Check if sleep intervals are not already in platform options
            if (! in_array('--sleep-interval', $platformOptions)) {
                $command[] = '--sleep-interval';
                $command[] = '1';
                $command[] = '--max-sleep-interval';
                $command[] = '3';
            }
        }

        if ($this->isYouTubeUrl($url)) {
            $command[] = '--extractor-args';
            $command[] = 'youtube:player-client=tv_embedded';
        }

        // Add URL last
        $command[] = $url;

        return $command;
    }

    /**
     * Check if URL is from Instagram.
     */
    private function isInstagramUrl(string $url): bool
    {
        return str_contains($url, 'instagram.com') || str_contains($url, 'instagr.am');
    }

    /**
     * Check if URL is from YouTube.
     */
    private function isYouTubeUrl(string $url): bool
    {
        return str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be');
    }

    /**
     * Determine platform from URL.
     */
    private function determinePlatformFromUrl(string $url): string
    {
        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        }

        if (str_contains($url, 'tiktok.com')) {
            return 'tiktok';
        }

        if (str_contains($url, 'instagram.com') || str_contains($url, 'instagr.am')) {
            return 'instagram';
        }

        if (str_contains($url, 'facebook.com') || str_contains($url, 'fb.watch')) {
            return 'facebook';
        }

        // Default fallback
        return 'unknown';
    }

    /**
     * Get platform-specific yt-dlp options based on URL.
     */
    private function getPlatformSpecificOptions(string $url): array
    {
        $options = [];

        if ($this->isInstagramUrl($url)) {
            // Add Instagram-specific options from config
            $instagramConfig = config('video-extraction.drivers.instagram.yt_dlp_options', []);
            foreach ($instagramConfig as $key => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $options[] = $key;
                    }
                } else {
                    $options[] = $key;
                    $options[] = $value;
                }
            }
        } elseif ($this->isYouTubeUrl($url)) {
            // Add YouTube-specific options from config
            $youtubeConfig = config('video-extraction.drivers.youtube.yt_dlp_options', []);
            foreach ($youtubeConfig as $key => $value) {
                if (is_bool($value)) {
                    if ($value) {
                        $options[] = $key;
                    }
                } else {
                    $options[] = $key;
                    $options[] = $value;
                }
            }
        }

        return $options;
    }

    /**
     * Execute yt-dlp command to get available formats for a video URL.
     *
     * @param  string  $url  The video URL
     * @return array Array of VideoFormat DTOs
     *
     * @throws YtDlpException
     */
    public function getAvailableFormats(string $url): array
    {
        try {
            Log::info('Executing yt-dlp command for URL', ['url' => $url]);

            // Build command with proper configuration
            $command = $this->buildYtDlpCommand($url, ['-F']);

            Log::info('Executing yt-dlp command', [
                'command' => implode(' ', $command),
                'binary_path' => config('video-extraction.yt_dlp.binary_path'),
                'timeout' => config('video-extraction.yt_dlp.timeout'),
            ]);

            // Execute yt-dlp -F command to list formats
            $result = Process::timeout(config('video-extraction.yt_dlp.timeout', 300))->run($command);

            if (! $result->successful()) {
                Log::error('yt-dlp command failed', [
                    'url' => $url,
                    'command' => implode(' ', $command),
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);

                throw new YtDlpException(
                    'yt-dlp command failed: '.$result->errorOutput(),
                    $result->exitCode()
                );
            }

            $output = $result->output();
            Log::debug('yt-dlp output received', ['output_length' => strlen($output)]);

            $formats = $this->parseFormatsOutput($output);

            $formats = array_filter($formats, function ($format) use ($url) {
                if (str_contains($url, 'youtube')) {
                    $ruleVideo = str_contains($format->vcodec, 'avc1') && $format->isVideoOnly;
                    $ruleAudio = str_contains($format->extension, 'm4a') && $format->isAudioOnly;

                    return $ruleVideo || $ruleAudio;
                }

                if (str_contains($url, 'facebook')) {
                    $ruleVideo = in_array($format->formatId, ['sd', 'hd']);
                    $ruleAudio = str_contains($format->extension, 'm4a') && $format->isAudioOnly;

                    return $ruleVideo || $ruleAudio;
                }

                return true;
            });

            $formats = array_map(function ($format) {
                if ($format->formatId === 'sd') {
                    $format->quality = 360;
                } elseif ($format->formatId === 'hd') {
                    $format->quality = 720;
                }

                return $format;
            }, $formats);

            // Get detailed format info to fill in missing file sizes
            $detailedInfo = $this->getDetailedFormatInfo($url);
            $formatSizes = $detailedInfo['format_sizes'] ?? [];

            // Update formats with file size information from detailed info
            foreach ($formats as $index => $format) {
                $formats[$index] = new VideoFormat(
                    formatId: $format->formatId,
                    extension: $format->extension,
                    resolution: $format->resolution,
                    fps: $format->fps,
                    filesize: $formatSizes[$format->formatId] ?? $format->filesize,
                    tbr: $format->tbr,
                    protocol: $format->protocol,
                    vcodec: $format->vcodec,
                    vbr: $format->vbr,
                    acodec: $format->acodec,
                    abr: $format->abr,
                    formatNote: $format->formatNote,
                    isVideoOnly: $format->isVideoOnly,
                    isAudioOnly: $format->isAudioOnly,
                    qualityLabel: $format->qualityLabel,
                    language: $format->language
                );
            }

            return $formats;

        } catch (\Exception $e) {
            Log::error('Failed to get available formats', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof YtDlpException) {
                throw $e;
            }

            throw new YtDlpException('Failed to execute yt-dlp: '.$e->getMessage());
        }
    }

    /**
     * Parse yt-dlp format output into structured data.
     *
     * @param  string  $output  Raw yt-dlp output
     * @return array Array of VideoFormat DTOs
     */
    private function parseFormatsOutput(string $output): array
    {
        $formats = [];
        $lines = explode("\n", $output);
        $headerFound = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Look for the header line to start parsing
            if (str_contains($line, 'ID') && str_contains($line, 'EXT') && str_contains($line, 'RESOLUTION')) {
                $headerFound = true;

                continue;
            }

            // Only parse format lines after header is found
            if (! $headerFound) {
                continue;
            }

            // Skip separator lines
            if (str_contains($line, '---') || str_contains($line, '===')) {
                continue;
            }

            $format = $this->parseFormatLine($line);
            if ($format) {
                $formats[] = $format;
            }
        }

        Log::info('Parsed formats from yt-dlp output', ['format_count' => count($formats)]);

        return $formats;
    }

    /**
     * Parse a single format line from yt-dlp output.
     *
     * @param  string  $line  Format line from yt-dlp
     */
    private function parseFormatLine(string $line): ?VideoFormat
    {
        // Split by whitespace but preserve quoted strings
        $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 3) {
            Log::debug('Skipping format line with insufficient parts', ['line' => $line, 'parts_count' => count($parts)]);

            return null;
        }

        try {
            $formatId = $parts[0] ?? '';
            $extension = $parts[1] ?? '';
            $resolution = $parts[2] ?? '';

            // Parse additional fields based on position
            $fps = null;
            $filesize = null;
            $tbr = null;
            $protocol = null;
            $vcodec = null;
            $vbr = null;
            $acodec = null;
            $abr = null;
            $formatNote = '';

            Log::debug('Parsing format line', [
                'format_id' => $formatId,
                'extension' => $extension,
                'resolution' => $resolution,
                'total_parts' => count($parts),
                'all_parts' => $parts,
            ]);

            // Try to extract numeric values and codecs from the remaining parts
            for ($i = 3; $i < count($parts); $i++) {
                $part = $parts[$i];

                // FPS detection (ends with 'fps')
                if (str_ends_with($part, 'fps') && is_numeric(str_replace('fps', '', $part))) {
                    $fps = (int) str_replace('fps', '', $part);
                }

                // File size detection (various formats including Instagram's approximate sizes)
                elseif (preg_match('/^[≈~]?(\d+(?:\.\d+)?)(B|KB|MB|GB|TB|KiB|MiB|GiB|TiB)$/i', $part, $matches)) {
                    $filesize = $this->convertToBytes($matches[1], $matches[2]);
                }

                // Bitrate detection (ends with 'k')
                elseif (str_ends_with($part, 'k') && is_numeric(str_replace('k', '', $part))) {
                    $bitrate = (int) str_replace('k', '', $part);
                    if (! $tbr) {
                        $tbr = $bitrate;
                    }
                }

                // Protocol detection
                elseif (in_array($part, ['https', 'http', 'm3u8', 'webm_dash', 'mp4_dash', 'dash'])) {
                    $protocol = $part;
                }

                // Codec detection (enhanced for Instagram DASH formats)
                elseif (preg_match('/^(h264|h265|vp9|vp8|av01|avc1|vp09\.\d+\.\d+\.\d+)/', $part)) {
                    $vcodec = $part;
                } elseif (preg_match('/^(aac|mp3|opus|vorbis|mp4a\.\d+\.\d+)/', $part)) {
                    $acodec = $part;
                }

                // Collect remaining as format note
                else {
                    $formatNote .= $part.' ';
                }
            }

            $formatNote = trim($formatNote);

            // Determine if it's video-only, audio-only, or combined (enhanced for Instagram DASH)
            $isAudioOnly = $resolution === 'audio only' ||
                          str_contains($formatNote, 'audio only') ||
                          str_contains($formatNote, 'DASH audio') ||
                          ($extension === 'm4a' && str_contains($formatNote, 'audio'));

            $isVideoOnly = ! $isAudioOnly &&
                          ($resolution !== 'audio only') &&
                          (str_contains($formatNote, 'video only') ||
                           str_contains($formatNote, 'DASH video') ||
                           ($vcodec && $vcodec !== 'none' && (! $acodec || $acodec === 'none')));

            // Extract quality label from resolution
            $qualityLabel = $this->extractQualityLabel($resolution);

            Log::debug('Parsed format data', [
                'format_id' => $formatId,
                'filesize' => $filesize,
                'fps' => $fps,
                'tbr' => $tbr,
                'vcodec' => $vcodec,
                'acodec' => $acodec,
                'protocol' => $protocol,
                'quality_label' => $qualityLabel,
                'is_video_only' => $isVideoOnly,
                'is_audio_only' => $isAudioOnly,
            ]);

            return new VideoFormat(
                formatId: $formatId,
                extension: $extension,
                resolution: $resolution,
                fps: $fps,
                filesize: $filesize,
                tbr: $tbr,
                protocol: $protocol,
                vcodec: $vcodec,
                vbr: $vbr,
                acodec: $acodec,
                abr: $abr,
                formatNote: $formatNote,
                isVideoOnly: $isVideoOnly,
                isAudioOnly: $isAudioOnly,
                qualityLabel: $qualityLabel
            );

        } catch (\Exception $e) {
            Log::warning('Failed to parse format line', [
                'line' => $line,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Convert file size to bytes.
     *
     * @param  string  $size  Size value (may include approximate symbol ≈)
     * @param  string  $unit  Size unit (B, KB, MB, GB, TB, KiB, MiB, GiB, TiB)
     * @return int Size in bytes
     */
    private function convertToBytes(string $size, string $unit): int
    {
        // Remove approximate symbol if present (Instagram uses ≈ for file sizes)
        $size = str_replace(['≈', '~'], '', $size);
        $size = (float) $size;

        return match (strtoupper($unit)) {
            'B' => (int) $size,
            'KB' => (int) ($size * 1000),
            'MB' => (int) ($size * 1000 * 1000),
            'GB' => (int) ($size * 1000 * 1000 * 1000),
            'TB' => (int) ($size * 1000 * 1000 * 1000 * 1000),
            'KIB' => (int) ($size * 1024),
            'MIB' => (int) ($size * 1024 * 1024),
            'GIB' => (int) ($size * 1024 * 1024 * 1024),
            'TIB' => (int) ($size * 1024 * 1024 * 1024 * 1024),
            default => (int) $size,
        };
    }

    /**
     * Extract quality label from resolution string.
     *
     * @param  string  $resolution  Resolution string
     * @return string|null Quality label (e.g., "720p", "1080p")
     */
    private function extractQualityLabel(string $resolution): ?string
    {
        if ($resolution === 'audio only') {
            return null;
        }

        // Extract height from resolution like "1920x1080"
        if (preg_match('/(\d+)x(\d+)/', $resolution, $matches)) {
            $height = (int) $matches[2];

            return $height.'p';
        }

        // Direct quality labels like "720p"
        if (preg_match('/(\d+p)/', $resolution, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get detailed format information and video metadata using JSON output.
     * This is used to get file sizes and extract video metadata.
     *
     * @param  string  $url  The video URL
     * @return array Array with 'format_sizes' and 'metadata' keys
     */
    public function getDetailedFormatInfo(string $url): array
    {
        try {
            Log::info('Getting detailed format info via JSON', ['url' => $url]);

            // Build command with proper configuration
            $command = $this->buildYtDlpCommand($url, ['--dump-json']);

            // Execute yt-dlp with JSON output to get detailed format information
            $result = Process::timeout(config('video-extraction.yt_dlp.timeout', 300))->run($command);

            if (! $result->successful()) {
                Log::warning('Failed to get detailed format info', [
                    'url' => $url,
                    'command' => implode(' ', $command),
                    'error' => $result->errorOutput(),
                ]);

                return [];
            }

            $jsonOutput = $result->output();
            $data = json_decode($jsonOutput, true);

            if (! $data || ! isset($data['formats'])) {
                Log::warning('Invalid JSON output from yt-dlp', ['url' => $url]);

                return ['format_sizes' => [], 'metadata' => []];
            }

            // Extract format file sizes
            $formatSizes = [];
            foreach ($data['formats'] as $format) {
                if (isset($format['format_id']) && isset($format['filesize'])) {
                    $formatSizes[$format['format_id']] = $format['filesize'];
                }
            }

            // Extract video metadata
            $metadata = $this->extractVideoMetadata($data, $url);

            Log::info('Retrieved format file sizes and metadata', [
                'url' => $url,
                'format_count' => count($formatSizes),
                'formats_with_size' => array_keys($formatSizes),
                'metadata_extracted' => ! empty($metadata),
                'metadata_fields' => array_keys($metadata),
            ]);

            return [
                'format_sizes' => $formatSizes,
                'metadata' => $metadata,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get detailed format info', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return ['format_sizes' => [], 'metadata' => []];
        }
    }

    /**
     * Extract video metadata from yt-dlp JSON output.
     *
     * @param  array  $data  The decoded JSON data from yt-dlp
     * @param  string  $url  The original video URL for platform detection
     * @return array Array with video metadata fields
     */
    private function extractVideoMetadata(array $data, string $url): array
    {
        $metadata = [];

        try {
            // Extract video ID
            if (isset($data['id'])) {
                $metadata['video_id'] = (string) $data['id'];
            } elseif (isset($data['video_id'])) {
                $metadata['video_id'] = (string) $data['video_id'];
            }

            // Extract title
            if (isset($data['title']) && ! empty($data['title'])) {
                $metadata['title'] = (string) $data['title'];
            }

            // Extract thumbnail URL with Instagram-specific fallbacks
            $thumbnailUrl = $this->extractThumbnailUrl($data);
            $metadata['thumbnail_url'] = $thumbnailUrl;

            // Download and store thumbnail if URL is available
            if ($thumbnailUrl && isset($metadata['video_id'])) {
                $platform = $this->determinePlatformFromUrl($url);
                $thumbnailInfo = $this->thumbnailService->downloadAndStore($thumbnailUrl, $metadata['video_id'], $platform);

                if ($thumbnailInfo) {
                    $metadata['thumbnail_disk'] = $thumbnailInfo['disk'];
                    $metadata['thumbnail_path'] = $thumbnailInfo['path'];

                    Log::info('Thumbnail downloaded and stored in YtDlpService', [
                        'video_id' => $metadata['video_id'],
                        'platform' => $platform,
                        'disk' => $thumbnailInfo['disk'],
                        'path' => $thumbnailInfo['path'],
                    ]);
                }
            }

            // Additional metadata for Instagram
            if (isset($data['uploader']) && ! empty($data['uploader'])) {
                $metadata['uploader'] = (string) $data['uploader'];
            }

            if (isset($data['description']) && ! empty($data['description'])) {
                $metadata['description'] = (string) $data['description'];
            }

            if (isset($data['upload_date']) && ! empty($data['upload_date'])) {
                $metadata['upload_date'] = (string) $data['upload_date'];
            }

            if (isset($data['view_count']) && is_numeric($data['view_count'])) {
                $metadata['view_count'] = (int) $data['view_count'];
            }

            // Extract duration (in seconds)
            if (isset($data['duration']) && is_numeric($data['duration'])) {
                $metadata['duration'] = (int) $data['duration'];
            }

            Log::debug('Extracted video metadata', [
                'video_id' => $metadata['video_id'] ?? 'not found',
                'title' => isset($metadata['title']) ? substr($metadata['title'], 0, 50).'...' : 'not found',
                'thumbnail_url' => isset($metadata['thumbnail_url']) ? 'found' : 'not found',
                'duration' => $metadata['duration'] ?? 'not found',
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to extract video metadata', [
                'error' => $e->getMessage(),
                'available_keys' => array_keys($data),
            ]);
        }

        return $metadata;
    }

    /**
     * Extract thumbnail URL with Instagram-specific fallbacks.
     */
    private function extractThumbnailUrl(array $data): ?string
    {
        // Primary thumbnail field
        if (isset($data['thumbnail']) && ! empty($data['thumbnail'])) {
            $thumbnail = (string) $data['thumbnail'];
            if ($this->isValidThumbnailUrl($thumbnail)) {
                Log::debug('Found primary thumbnail', ['url' => $thumbnail]);

                return $thumbnail;
            }
        }

        // Instagram-specific thumbnail fields
        $instagramThumbnailFields = [
            'thumbnails',
            'thumbnail_url',
            'display_url',
            'thumbnail_src',
        ];

        foreach ($instagramThumbnailFields as $field) {
            if (isset($data[$field])) {
                $thumbnailData = $data[$field];

                // Handle array of thumbnails (common in Instagram)
                if (is_array($thumbnailData)) {
                    $thumbnail = $this->selectBestThumbnail($thumbnailData);
                    if ($thumbnail && $this->isValidThumbnailUrl($thumbnail)) {
                        Log::debug('Found thumbnail from array', ['field' => $field, 'url' => $thumbnail]);

                        return $thumbnail;
                    }
                } elseif (is_string($thumbnailData) && ! empty($thumbnailData)) {
                    if ($this->isValidThumbnailUrl($thumbnailData)) {
                        Log::debug('Found thumbnail from field', ['field' => $field, 'url' => $thumbnailData]);

                        return $thumbnailData;
                    }
                }
            }
        }

        Log::warning('No valid thumbnail URL found', [
            'available_fields' => array_keys($data),
            'thumbnail_fields_checked' => array_merge(['thumbnail'], $instagramThumbnailFields),
        ]);

        return null;
    }

    /**
     * Select the best thumbnail from an array of thumbnail options.
     */
    private function selectBestThumbnail(array $thumbnails): ?string
    {
        if (empty($thumbnails)) {
            return null;
        }

        // If it's a simple array of URLs
        if (isset($thumbnails[0]) && is_string($thumbnails[0])) {
            return $thumbnails[0]; // Return first URL
        }

        // If it's an array of thumbnail objects with metadata
        $bestThumbnail = null;
        $bestScore = 0;

        foreach ($thumbnails as $thumbnail) {
            if (! is_array($thumbnail)) {
                continue;
            }

            $url = $thumbnail['url'] ?? null;
            if (! $url || ! $this->isValidThumbnailUrl($url)) {
                continue;
            }

            // Score based on resolution (prefer higher resolution)
            $score = 0;
            if (isset($thumbnail['width']) && isset($thumbnail['height'])) {
                $score = (int) $thumbnail['width'] * (int) $thumbnail['height'];
            } elseif (isset($thumbnail['preference'])) {
                $score = (int) $thumbnail['preference'] * 1000000; // Preference is usually small numbers
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestThumbnail = $url;
            }
        }

        return $bestThumbnail ?: ($thumbnails[0]['url'] ?? null);
    }

    /**
     * Validate if a thumbnail URL is valid and accessible.
     */
    private function isValidThumbnailUrl(string $url): bool
    {
        // Basic URL validation
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check if it's an image URL (common extensions)
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $urlPath = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        // Allow URLs without extensions (Instagram often uses parameterized URLs)
        if (empty($extension)) {
            return true;
        }

        // For Instagram URLs, validate that essential parameters are present
        if (str_contains($url, 'instagram.') || str_contains($url, 'fbcdn.net')) {
            return $this->validateInstagramThumbnailUrl($url);
        }

        return in_array($extension, $imageExtensions);
    }

    /**
     * Validate Instagram thumbnail URL has required parameters.
     */
    private function validateInstagramThumbnailUrl(string $url): bool
    {
        $parsedUrl = parse_url($url);

        if (! isset($parsedUrl['query'])) {
            Log::warning('Instagram thumbnail URL missing query parameters', ['url' => $url]);

            return false;
        }

        parse_str($parsedUrl['query'], $queryParams);

        // Check for essential Instagram parameters
        $requiredParams = ['_nc_ht', '_nc_cat', 'oh', 'oe'];
        $missingParams = [];

        foreach ($requiredParams as $param) {
            if (! isset($queryParams[$param])) {
                $missingParams[] = $param;
            }
        }

        if (! empty($missingParams)) {
            Log::warning('Instagram thumbnail URL missing required parameters', [
                'url' => substr($url, 0, 100).'...',
                'missing_params' => $missingParams,
            ]);

            return false;
        }

        // Check if URL might be expired (oe parameter is expiration timestamp)
        if (isset($queryParams['oe'])) {
            $expirationTimestamp = hexdec($queryParams['oe']);
            if ($expirationTimestamp > 0 && $expirationTimestamp < time()) {
                Log::warning('Instagram thumbnail URL appears to be expired', [
                    'url' => substr($url, 0, 100).'...',
                    'expiration_timestamp' => $expirationTimestamp,
                    'current_timestamp' => time(),
                ]);
                // Still return true as the URL might work despite appearing expired
            }
        }

        return true;
    }

    /**
     * Refresh Instagram thumbnail URL if expired or invalid.
     */
    public function refreshInstagramThumbnail(string $videoUrl): ?string
    {
        try {
            Log::info('Refreshing Instagram thumbnail', ['video_url' => $videoUrl]);

            $metadata = $this->getVideoMetadata($videoUrl);

            if (isset($metadata['thumbnail_url'])) {
                Log::info('Instagram thumbnail refreshed successfully', [
                    'video_url' => $videoUrl,
                    'new_thumbnail' => substr($metadata['thumbnail_url'], 0, 100).'...',
                ]);

                return $metadata['thumbnail_url'];
            }

            Log::warning('Failed to refresh Instagram thumbnail', ['video_url' => $videoUrl]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error refreshing Instagram thumbnail', [
                'video_url' => $videoUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get video metadata only (without format information).
     *
     * @param  string  $url  The video URL
     * @return array Array with video metadata fields
     */
    public function getVideoMetadata(string $url): array
    {
        $detailedInfo = $this->getDetailedFormatInfo($url);

        return $detailedInfo['metadata'] ?? [];
    }

    /**
     * Download video using yt-dlp command with optional video+audio merging.
     *
     * @param  string  $originUrl  The original video URL
     * @param  string  $cdnId  The CDN format ID to download
     * @param  string|null  $outputDirectory  Optional output directory (defaults to temp directory)
     * @param  string|null  $downloadSessionId  Optional download session ID for video+audio merging
     * @param  string|null  $audioCdnId  Optional audio CDN ID for merging
     * @return array Download result with file path, size, and metadata
     *
     * @throws YtDlpException
     */
    public function downloadVideo(string $originUrl, string $cdnId, ?string $outputDirectory = null, ?string $downloadSessionId = null, ?string $audioCdnId = null): array
    {
        try {
            // Use configured temp directory if none provided
            $outputDirectory = $outputDirectory ?? config('video-extraction.temp.directory');

            // Ensure output directory exists
            if (! is_dir($outputDirectory)) {
                mkdir($outputDirectory, 0755, true);
            }

            // Determine format string - try to merge video+audio if possible
            $formatString = $this->buildFormatString($cdnId, $downloadSessionId, $originUrl);

            // Generate stable filename using hash
            $stableFilename = $this->filenameService->generateStableFilename($originUrl, $cdnId, $downloadSessionId);

            Log::info('Starting video download with yt-dlp', [
                'url' => $originUrl,
                'cdn_id' => $cdnId,
                'format_string' => $formatString,
                'output_directory' => $outputDirectory,
                'stable_filename' => $stableFilename,
                'download_session_id' => $downloadSessionId,
            ]);

            // Build download command with proper configuration
            $downloadOptions = [
                '-f', $formatString,
                '-P', $outputDirectory,
                '-o', $stableFilename,
                '--print', 'after_move:filepath',
                '--print', 'filesize',
                '--print', 'title',
            ];

            $command = $this->buildYtDlpCommand($originUrl, $downloadOptions);

            Log::info('Executing yt-dlp download command', [
                'command' => implode(' ', $command),
                'format_string' => $formatString,
                'output_directory' => $outputDirectory,
            ]);

            // Execute yt-dlp download command with stable filename
            $result = Process::timeout(config('video-extraction.yt_dlp.download_timeout', 600))->run($command);

            if (! $result->successful()) {
                Log::error('yt-dlp download command failed', [
                    'url' => $originUrl,
                    'command' => implode(' ', $command),
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'output' => $result->output(),
                ]);

                throw new YtDlpException(
                    'yt-dlp download failed: '.$result->errorOutput(),
                    $result->exitCode()
                );
            }

            $output = trim($result->output());
            $lines = explode("\n", $output);

            // Parse output - last 3 lines should be filepath, filesize, title
            $outputLines = array_filter($lines, fn ($line) => ! empty(trim($line)));
            $outputLines = array_values($outputLines);

            if (count($outputLines) < 3) {
                throw new YtDlpException('Unexpected yt-dlp output format');
            }

            $filePath = end($outputLines);
            $fileSize = prev($outputLines);
            $title = prev($outputLines);

            // Verify file exists
            if (! file_exists($filePath)) {
                throw new YtDlpException('Downloaded file not found: '.$filePath);
            }

            $actualFileSize = filesize($filePath);

            Log::info('Video download completed successfully', [
                'url' => $originUrl,
                'cdn_id' => $cdnId,
                'file_path' => $filePath,
                'file_size' => $actualFileSize,
                'title' => $title,
            ]);

            // Convert to MP4 if necessary
            $conversionResult = $this->convertToMp4($filePath);

            // Update file path and size if conversion occurred
            if ($conversionResult['converted']) {
                $filePath = $conversionResult['output_path'];
                $actualFileSize = $conversionResult['converted_size'];

                Log::info('Video converted to MP4 format', [
                    'original_path' => $conversionResult['original_path'],
                    'converted_path' => $filePath,
                    'original_size' => $conversionResult['original_size'],
                    'converted_size' => $actualFileSize,
                ]);
            }

            return [
                'file_path' => $filePath,
                'file_size' => $actualFileSize,
                'title' => $title,
                'cdn_id' => $cdnId,
                'original_url' => $originUrl,
                'converted_to_mp4' => $conversionResult['converted'],
            ];

        } catch (\Exception $e) {
            Log::error('Video download failed', [
                'url' => $originUrl,
                'cdn_id' => $cdnId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new YtDlpException(
                'Failed to download video: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Convert video file to MP4 format using FFmpeg.
     *
     * @param  string  $inputPath  Path to the input video file
     * @return array Result with converted file path and metadata
     *
     * @throws YtDlpException
     */
    private function convertToMp4(string $inputPath): array
    {
        try {
            $inputInfo = pathinfo($inputPath);
            $outputPath = $inputInfo['dirname'].'/'.$inputInfo['filename'].'.mp4';

            // Skip conversion if already MP4
            if (strtolower($inputInfo['extension'] ?? '') === 'mp4') {
                Log::info('File is already MP4, skipping conversion', [
                    'file_path' => $inputPath,
                ]);

                return [
                    'converted' => false,
                    'output_path' => $inputPath,
                    'original_path' => $inputPath,
                ];
            }

            Log::info('Starting FFmpeg conversion to MP4 with Apple compatibility', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'input_extension' => $inputInfo['extension'] ?? 'unknown',
                'apple_compatibility' => $appleCompatibility ?? false,
                'video_profile' => $videoProfile ?? 'not_set',
                'pixel_format' => $pixelFormat ?? 'not_set',
            ]);

            // Check if FFmpeg conversion is enabled
            if (! config('video-extraction.ffmpeg.enabled', true)) {
                Log::info('FFmpeg conversion is disabled, returning original file', [
                    'file_path' => $inputPath,
                ]);

                return [
                    'converted' => false,
                    'output_path' => $inputPath,
                    'original_path' => $inputPath,
                ];
            }

            // Build FFmpeg command for Apple-compatible conversion using config values
            $ffmpegBinary = config('video-extraction.ffmpeg.binary_path', 'ffmpeg');
            $crf = config('video-extraction.ffmpeg.quality.crf', 23);
            $preset = config('video-extraction.ffmpeg.quality.preset', 'medium');
            $audioBitrate = config('video-extraction.ffmpeg.quality.audio_bitrate', '128k');
            $audioSampleRate = config('video-extraction.ffmpeg.quality.audio_sample_rate', '44100');

            // Apple compatibility settings
            $appleCompatibility = config('video-extraction.ffmpeg.apple_compatibility.enabled', true);
            $videoProfile = config('video-extraction.ffmpeg.apple_compatibility.video_profile', 'high');
            $videoLevel = config('video-extraction.ffmpeg.apple_compatibility.video_level', '4.0');
            $pixelFormat = config('video-extraction.ffmpeg.apple_compatibility.pixel_format', 'yuv420p');
            $maxWidth = config('video-extraction.ffmpeg.apple_compatibility.max_width', 1920);
            $maxHeight = config('video-extraction.ffmpeg.apple_compatibility.max_height', 1080);
            $maxBitrate = config('video-extraction.ffmpeg.apple_compatibility.max_bitrate', '5000k');

            // Build comprehensive FFmpeg command for Apple compatibility
            $ffmpegCommand = [
                $ffmpegBinary,
                '-i', $inputPath,
            ];

            // Add Apple compatibility settings if enabled
            if ($appleCompatibility) {
                // Video encoding with explicit Apple-compatible settings
                $ffmpegCommand = array_merge($ffmpegCommand, [
                    // Video codec and quality settings
                    '-c:v', 'libx264',
                    '-profile:v', $videoProfile,
                    '-level:v', $videoLevel,
                    '-pix_fmt', $pixelFormat,
                    '-crf', (string) $crf,
                    '-preset', $preset,

                    // Video constraints for Apple devices
                    '-vf', "scale='min({$maxWidth},iw)':'min({$maxHeight},ih)':force_original_aspect_ratio=decrease:flags=lanczos",
                    '-maxrate', $maxBitrate,
                    '-bufsize', '10000k',

                    // Audio encoding
                    '-c:a', 'aac',
                    '-b:a', $audioBitrate,
                    '-ar', $audioSampleRate,
                    '-ac', '2', // Stereo audio

                    // Stream mapping to ensure both video and audio are included
                    '-map', '0:v:0', // Map first video stream
                    '-map', '0:a:0', // Map first audio stream

                    // MP4 container optimization for Apple devices
                    '-movflags', '+faststart+use_metadata_tags',
                    '-f', 'mp4',

                    // Overwrite output file
                    '-y',
                    $outputPath,
                ]);
            } else {
                // Fallback to basic conversion
                $ffmpegCommand = array_merge($ffmpegCommand, [
                    '-c:v', 'libx264',
                    '-crf', (string) $crf,
                    '-preset', $preset,
                    '-c:a', 'aac',
                    '-b:a', $audioBitrate,
                    '-ar', $audioSampleRate,
                    '-movflags', '+faststart',
                    '-f', 'mp4',
                    '-y',
                    $outputPath,
                ]);
            }

            Log::info('Executing FFmpeg conversion command', [
                'command' => implode(' ', $ffmpegCommand),
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'apple_compatibility' => $appleCompatibility,
            ]);

            // Execute FFmpeg conversion
            $result = Process::timeout(config('video-extraction.yt_dlp.download_timeout', 600))
                ->run($ffmpegCommand);

            // If Apple compatibility mode failed, try fallback conversion
            if (! $result->successful() && $appleCompatibility) {
                Log::warning('Apple compatibility conversion failed, trying fallback', [
                    'input_path' => $inputPath,
                    'error_output' => $result->errorOutput(),
                ]);

                // Build fallback command without explicit stream mapping
                $fallbackCommand = [
                    $ffmpegBinary,
                    '-i', $inputPath,
                    '-c:v', 'libx264',
                    '-profile:v', 'high',
                    '-level:v', '4.0',
                    '-pix_fmt', 'yuv420p',
                    '-crf', '23',
                    '-preset', 'medium',
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-ar', '44100',
                    '-movflags', '+faststart',
                    '-f', 'mp4',
                    '-y',
                    $outputPath,
                ];

                Log::info('Executing fallback FFmpeg conversion', [
                    'command' => implode(' ', $fallbackCommand),
                ]);

                $result = Process::timeout(config('video-extraction.yt_dlp.download_timeout', 600))
                    ->run($fallbackCommand);
            }

            if (! $result->successful()) {
                Log::error('FFmpeg conversion failed (including fallback)', [
                    'input_path' => $inputPath,
                    'output_path' => $outputPath,
                    'command' => implode(' ', $ffmpegCommand),
                    'exit_code' => $result->exitCode(),
                    'error_output' => $result->errorOutput(),
                    'stdout' => $result->output(),
                ]);

                throw new YtDlpException(
                    'FFmpeg conversion failed: '.$result->errorOutput(),
                    $result->exitCode()
                );
            }

            // Verify converted file exists and has reasonable size
            if (! file_exists($outputPath)) {
                throw new YtDlpException('Converted MP4 file not found: '.$outputPath);
            }

            $originalSize = filesize($inputPath);
            $convertedSize = filesize($outputPath);

            // Sanity check: converted file should not be too small (less than 10% of original)
            if ($convertedSize < ($originalSize * 0.1)) {
                Log::warning('Converted file seems too small, may be corrupted', [
                    'original_size' => $originalSize,
                    'converted_size' => $convertedSize,
                    'input_path' => $inputPath,
                    'output_path' => $outputPath,
                ]);
            }

            // Validate Apple compatibility if enabled
            if ($appleCompatibility) {
                $this->validateAppleCompatibility($outputPath);
            }

            // Remove original file to save space
            if (unlink($inputPath)) {
                Log::info('Original file removed after successful conversion', [
                    'removed_file' => $inputPath,
                ]);
            } else {
                Log::warning('Failed to remove original file after conversion', [
                    'file_path' => $inputPath,
                ]);
            }

            Log::info('FFmpeg conversion completed successfully', [
                'input_path' => $inputPath,
                'output_path' => $outputPath,
                'original_size' => $originalSize,
                'converted_size' => $convertedSize,
                'size_ratio' => round($convertedSize / $originalSize, 2),
            ]);

            return [
                'converted' => true,
                'output_path' => $outputPath,
                'original_path' => $inputPath,
                'original_size' => $originalSize,
                'converted_size' => $convertedSize,
            ];

        } catch (\Exception $e) {
            Log::error('Video conversion failed', [
                'input_path' => $inputPath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($e instanceof YtDlpException) {
                throw $e;
            }

            throw new YtDlpException(
                'Failed to convert video to MP4: '.$e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Build format string for yt-dlp, attempting video+audio merging when possible.
     *
     * @param  string  $cdnId  The CDN format ID to download
     * @param  string|null  $downloadSessionId  Optional download session ID for video+audio merging
     * @param  string|null  $audioCdnId  Optional audio CDN ID for merging
     * @return string Format string for yt-dlp command
     */
    private function buildFormatString(string $cdnId, ?string $downloadSessionId = null, ?string $originUrl = null): string
    {
        // If no session ID provided or this is an audio format, use single format
        if (! $downloadSessionId || $this->isAudioFormat($cdnId)) {
            return $cdnId;
        }

        // Try to find corresponding audio option for video formats
        if ($this->isVideoFormat($cdnId)) {
            $audioOption = DownloadOption::query()
                ->where('download_session_id', $downloadSessionId)
                ->where('quality', 'audio')
                ->first();

            if ($audioOption && $audioOption->cdn_id) {
                Log::info('Found audio format for video+audio merging', [
                    'video_format' => $cdnId,
                    'audio_format' => $audioOption->cdn_id,
                    'download_session_id' => $downloadSessionId,
                ]);

                return $cdnId.'+'.$audioOption->cdn_id;
            } else {
                Log::info('No audio format found, using video-only format', [
                    'video_format' => $cdnId,
                    'download_session_id' => $downloadSessionId,
                ]);
            }
        }

        // Fallback to single format
        return $cdnId;
    }

    /**
     * Check if the given format ID represents a video format.
     *
     * @param  string  $cdnId  Format ID to check
     * @return bool True if this is likely a video format
     */
    private function isVideoFormat(string $cdnId): bool
    {
        // This is a simple heuristic - could be enhanced with more sophisticated detection
        $audioKeywords = ['audio', 'mp3', 'aac', 'opus', 'vorbis'];

        foreach ($audioKeywords as $keyword) {
            if (stripos($cdnId, $keyword) !== false) {
                return false;
            }
        }

        return true; // Assume it's video if not clearly audio
    }

    /**
     * Check if the given format ID represents an audio format.
     *
     * @param  string  $cdnId  Format ID to check
     * @return bool True if this is likely an audio format
     */
    private function isAudioFormat(string $cdnId): bool
    {
        return ! $this->isVideoFormat($cdnId);
    }

    /**
     * Validate that the converted video meets Apple device compatibility requirements.
     *
     * @param  string  $filePath  Path to the converted video file
     *
     * @throws YtDlpException If validation fails
     */
    private function validateAppleCompatibility(string $filePath): void
    {
        try {
            // Use ffprobe to check video properties
            $ffprobeBinary = str_replace('ffmpeg', 'ffprobe', config('video-extraction.ffmpeg.binary_path', 'ffmpeg'));

            $ffprobeCommand = [
                $ffprobeBinary,
                '-v', 'quiet',
                '-print_format', 'json',
                '-show_format',
                '-show_streams',
                $filePath,
            ];

            $result = Process::timeout(30)->run($ffprobeCommand);

            if (! $result->successful()) {
                Log::warning('Failed to validate Apple compatibility - ffprobe failed', [
                    'file_path' => $filePath,
                    'error' => $result->errorOutput(),
                ]);

                return; // Don't fail the conversion, just log the warning
            }

            $videoInfo = json_decode($result->output(), true);

            if (! $videoInfo || ! isset($videoInfo['streams'])) {
                Log::warning('Failed to parse video information for Apple compatibility check', [
                    'file_path' => $filePath,
                ]);

                return;
            }

            // Check both video and audio stream properties
            $hasVideo = false;
            $hasAudio = false;

            foreach ($videoInfo['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    $hasVideo = true;
                    $codecName = $stream['codec_name'] ?? 'unknown';
                    $profile = $stream['profile'] ?? 'unknown';
                    $pixelFormat = $stream['pix_fmt'] ?? 'unknown';
                    $width = $stream['width'] ?? 0;
                    $height = $stream['height'] ?? 0;
                    $bitRate = $stream['bit_rate'] ?? 'unknown';

                    Log::info('Video stream properties for Apple compatibility check', [
                        'file_path' => $filePath,
                        'codec' => $codecName,
                        'profile' => $profile,
                        'pixel_format' => $pixelFormat,
                        'resolution' => "{$width}x{$height}",
                        'bit_rate' => $bitRate,
                    ]);

                    // Validate codec
                    if ($codecName !== 'h264') {
                        Log::error('CRITICAL: Video codec is not H.264, will not play on Apple devices', [
                            'file_path' => $filePath,
                            'codec' => $codecName,
                            'expected' => 'h264',
                        ]);
                    }

                    // Validate pixel format
                    if ($pixelFormat !== 'yuv420p') {
                        Log::error('CRITICAL: Pixel format is not yuv420p, will not play on Apple devices', [
                            'file_path' => $filePath,
                            'pixel_format' => $pixelFormat,
                            'expected' => 'yuv420p',
                        ]);
                    }

                    // Validate profile
                    if (! in_array(strtolower($profile), ['high', 'main'])) {
                        Log::warning('Video profile may not be optimal for Apple devices', [
                            'file_path' => $filePath,
                            'profile' => $profile,
                            'recommended' => 'High or Main',
                        ]);
                    }
                }

                if ($stream['codec_type'] === 'audio') {
                    $hasAudio = true;
                    $audioCodec = $stream['codec_name'] ?? 'unknown';
                    $sampleRate = $stream['sample_rate'] ?? 'unknown';
                    $channels = $stream['channels'] ?? 'unknown';

                    Log::info('Audio stream properties for Apple compatibility check', [
                        'file_path' => $filePath,
                        'codec' => $audioCodec,
                        'sample_rate' => $sampleRate,
                        'channels' => $channels,
                    ]);

                    if ($audioCodec !== 'aac') {
                        Log::warning('Audio codec is not AAC, may have compatibility issues', [
                            'file_path' => $filePath,
                            'codec' => $audioCodec,
                            'expected' => 'aac',
                        ]);
                    }
                }
            }

            // Check if both streams are present
            if (! $hasVideo) {
                Log::error('CRITICAL: No video stream found in converted file', [
                    'file_path' => $filePath,
                ]);
            }

            if (! $hasAudio) {
                Log::warning('No audio stream found in converted file', [
                    'file_path' => $filePath,
                ]);
            }

            Log::info('Apple compatibility validation completed', [
                'file_path' => $filePath,
                'container_format' => $videoInfo['format']['format_name'] ?? 'unknown',
            ]);

        } catch (\Exception $e) {
            Log::warning('Apple compatibility validation failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception - validation failure shouldn't stop the conversion
        }
    }
}
