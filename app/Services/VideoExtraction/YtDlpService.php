<?php

namespace App\Services\VideoExtraction;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use App\Services\VideoExtraction\DTOs\VideoFormat;
use App\Services\VideoExtraction\Exceptions\YtDlpException;

/**
 * Service for executing yt-dlp commands and parsing video format information.
 */
class YtDlpService
{
    /**
     * Execute yt-dlp command to get available formats for a video URL.
     *
     * @param string $url The video URL
     * @return array Array of VideoFormat DTOs
     * @throws YtDlpException
     */
    public function getAvailableFormats(string $url): array
    {
        try {
            Log::info('Executing yt-dlp command for URL', ['url' => $url]);

            // Execute yt-dlp -F command to list formats
            $result = Process::timeout(60)->run([
                'yt-dlp',
                '-F',
                '--no-warnings',
                '--no-playlist',
                $url
            ]);

            if (!$result->successful()) {
                throw new YtDlpException(
                    'yt-dlp command failed: ' . $result->errorOutput(),
                    $result->exitCode()
                );
            }

            $output = $result->output();
            Log::debug('yt-dlp output received', ['output_length' => strlen($output)]);

            return $this->parseFormatsOutput($output);

        } catch (\Exception $e) {
            Log::error('Failed to get available formats', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            if ($e instanceof YtDlpException) {
                throw $e;
            }

            throw new YtDlpException('Failed to execute yt-dlp: ' . $e->getMessage());
        }
    }

    /**
     * Parse yt-dlp format output into structured data.
     *
     * @param string $output Raw yt-dlp output
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
            if (!$headerFound) {
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
     * @param string $line Format line from yt-dlp
     * @return VideoFormat|null
     */
    private function parseFormatLine(string $line): ?VideoFormat
    {
        // Split by whitespace but preserve quoted strings
        $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) < 3) {
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

            // Try to extract numeric values and codecs from the remaining parts
            for ($i = 3; $i < count($parts); $i++) {
                $part = $parts[$i];

                // FPS detection (ends with 'fps')
                if (str_ends_with($part, 'fps') && is_numeric(str_replace('fps', '', $part))) {
                    $fps = (int) str_replace('fps', '', $part);
                }

                // File size detection (ends with size units)
                elseif (preg_match('/^(\d+(?:\.\d+)?)(B|KB|MB|GB)$/i', $part, $matches)) {
                    $filesize = $this->convertToBytes($matches[1], $matches[2]);
                }

                // Bitrate detection (ends with 'k')
                elseif (str_ends_with($part, 'k') && is_numeric(str_replace('k', '', $part))) {
                    $bitrate = (int) str_replace('k', '', $part);
                    if (!$tbr) $tbr = $bitrate;
                }

                // Protocol detection
                elseif (in_array($part, ['https', 'http', 'm3u8', 'webm_dash', 'mp4_dash', 'dash'])) {
                    $protocol = $part;
                }

                // Codec detection
                elseif (preg_match('/^(h264|h265|vp9|vp8|av01|avc1)/', $part)) {
                    $vcodec = $part;
                }
                elseif (preg_match('/^(aac|mp3|opus|vorbis)/', $part)) {
                    $acodec = $part;
                }

                // Collect remaining as format note
                else {
                    $formatNote .= $part . ' ';
                }
            }

            $formatNote = trim($formatNote);

            // Determine if it's video-only, audio-only, or combined
            $isVideoOnly = $resolution !== 'audio only' && ($acodec === null || $acodec === 'none');
            $isAudioOnly = $resolution === 'audio only' || str_contains($formatNote, 'audio only');

            // Extract quality label from resolution
            $qualityLabel = $this->extractQualityLabel($resolution);

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
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Convert file size to bytes.
     *
     * @param string $size Size value
     * @param string $unit Size unit (B, KB, MB, GB)
     * @return int Size in bytes
     */
    private function convertToBytes(string $size, string $unit): int
    {
        $size = (float) $size;

        return match (strtoupper($unit)) {
            'B' => (int) $size,
            'KB' => (int) ($size * 1024),
            'MB' => (int) ($size * 1024 * 1024),
            'GB' => (int) ($size * 1024 * 1024 * 1024),
            default => (int) $size,
        };
    }

    /**
     * Extract quality label from resolution string.
     *
     * @param string $resolution Resolution string
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
            return $height . 'p';
        }

        // Direct quality labels like "720p"
        if (preg_match('/(\d+p)/', $resolution, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
