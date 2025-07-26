<?php

namespace App\Services\VideoExtraction;

use App\Models\DownloadOption;
use App\Services\VideoExtraction\DTOs\VideoFormat;
use App\Services\VideoExtraction\Exceptions\YtDlpException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Service for executing yt-dlp commands and parsing video format information.
 */
class YtDlpService
{
    protected FilenameService $filenameService;

    public function __construct(FilenameService $filenameService)
    {
        $this->filenameService = $filenameService;
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

        // Add additional options first
        $command = array_merge($command, $additionalOptions);

        // Add standard options
        if (config('video-extraction.yt_dlp.no_warnings', true)) {
            $command[] = '--no-warnings';
        }

        $command[] = '--no-playlist';

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

            // Get detailed format info to fill in missing file sizes
            $detailedInfo = $this->getDetailedFormatInfo($url);
            $formatSizes = $detailedInfo['format_sizes'] ?? [];

            // Update formats with file size information from detailed info
            foreach ($formats as $index => $format) {
                if (! $format->filesize && isset($formatSizes[$format->formatId])) {
                    // Create a new VideoFormat with the updated file size
                    $formats[$index] = new VideoFormat(
                        formatId: $format->formatId,
                        extension: $format->extension,
                        resolution: $format->resolution,
                        fps: $format->fps,
                        filesize: $formatSizes[$format->formatId],
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
            $metadata = $this->extractVideoMetadata($data);

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
     * @return array Array with video metadata fields
     */
    private function extractVideoMetadata(array $data): array
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
            $metadata['thumbnail_url'] = $this->extractThumbnailUrl($data);

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

        return in_array($extension, $imageExtensions);
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

            return [
                'file_path' => $filePath,
                'file_size' => $actualFileSize,
                'title' => $title,
                'cdn_id' => $cdnId,
                'original_url' => $originUrl,
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
     * Build format string for yt-dlp, attempting video+audio merging when possible.
     * For Instagram, uses quality-based selectors instead of specific format IDs.
     *
     * @param  string  $cdnId  The primary format ID to download
     * @param  string|null  $downloadSessionId  Optional session ID to find audio format
     * @param  string|null  $originUrl  The original video URL for platform detection
     * @return string Format string for yt-dlp command
     */
    private function buildFormatString(string $cdnId, ?string $downloadSessionId = null, ?string $originUrl = null): string
    {
        // Check if this is an Instagram URL and handle specially
        if ($originUrl && $this->isInstagramUrl($originUrl)) {
            return $this->buildInstagramFormatString($cdnId, $downloadSessionId);
        }

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
     * Build Instagram-specific format string using quality-based selectors.
     * Instagram DASH format IDs can be temporary, so we use quality selectors instead.
     */
    private function buildInstagramFormatString(string $cdnId, ?string $downloadSessionId = null): string
    {
        // Get the download option to determine the quality
        if ($downloadSessionId) {
            $downloadOption = DownloadOption::query()
                ->where('download_session_id', $downloadSessionId)
                ->where('cdn_id', $cdnId)
                ->first();

            if ($downloadOption) {
                $quality = $downloadOption->quality;

                Log::info('Building Instagram format string', [
                    'cdn_id' => $cdnId,
                    'quality' => $quality,
                    'download_session_id' => $downloadSessionId,
                ]);

                // Use quality-based format selectors for Instagram
                return $this->getInstagramFormatSelector($quality, $downloadSessionId);
            }
        }

        // Fallback to original format ID if we can't determine quality
        Log::warning('Could not determine quality for Instagram download, using original format ID', [
            'cdn_id' => $cdnId,
            'download_session_id' => $downloadSessionId,
        ]);

        return $cdnId;
    }

    /**
     * Get Instagram format selector based on quality.
     */
    private function getInstagramFormatSelector(string $quality, ?string $downloadSessionId = null): string
    {
        return match ($quality) {
            'audio' => 'bestaudio[ext=m4a]/bestaudio',
            '1080' => $this->buildInstagramVideoSelector('1080', $downloadSessionId),
            '720' => $this->buildInstagramVideoSelector('720', $downloadSessionId),
            '360' => $this->buildInstagramVideoSelector('360', $downloadSessionId),
            '144' => $this->buildInstagramVideoSelector('144', $downloadSessionId),
            default => 'best', // Fallback to simple 'best' for unknown qualities
        };
    }

    /**
     * Build Instagram video format selector with optional audio merging.
     */
    private function buildInstagramVideoSelector(string $quality, ?string $downloadSessionId = null): string
    {
        $heightLimit = match ($quality) {
            '1080' => '1920', // Instagram 1080p is actually 1080x1920
            '720' => '1280',  // Instagram 720p is actually 720x1280
            '360' => '640',   // Instagram 360p is actually 360x640
            '144' => '480',   // Instagram 144p fallback
            default => '1920',
        };

        // Try to merge with audio if available
        if ($downloadSessionId) {
            $audioOption = DownloadOption::query()
                ->where('download_session_id', $downloadSessionId)
                ->where('quality', 'audio')
                ->first();

            if ($audioOption) {
                Log::info('Instagram video+audio merge available', [
                    'video_quality' => $quality,
                    'height_limit' => $heightLimit,
                ]);

                // Use format selector that will merge video and audio
                // Remove the optional ? syntax that causes issues with Instagram
                return "best[height<={$heightLimit}][vcodec!=none]+bestaudio[ext=m4a]/best[height<={$heightLimit}]";
            }
        }

        // Video-only format - use simpler selectors that work with Instagram
        Log::info('Instagram video-only format', [
            'video_quality' => $quality,
            'height_limit' => $heightLimit,
        ]);

        // Use multiple fallback options for better compatibility
        return "best[height<={$heightLimit}][vcodec!=none]/best[height<={$heightLimit}]/best";
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
}
