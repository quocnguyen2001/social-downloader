<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for downloading and storing video thumbnails.
 */
class ThumbnailService
{
    /**
     * Download thumbnail from URL and store it to configured storage disk.
     *
     * @param  string  $thumbnailUrl  The thumbnail URL to download
     * @param  string  $videoId  The video ID for filename generation
     * @param  string  $platform  The platform name for organization
     * @return array|null Array with 'disk' and 'path' keys, or null if failed
     */
    public function downloadAndStore(string $thumbnailUrl, string $videoId, string $platform): ?array
    {
        try {
            Log::info('Starting thumbnail download', [
                'url' => $thumbnailUrl,
                'video_id' => $videoId,
                'platform' => $platform,
            ]);

            // Get the configured storage disk
            $disk = 'public';

            // Download the thumbnail
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->get($thumbnailUrl);

            if (! $response->successful()) {
                Log::warning('Failed to download thumbnail', [
                    'url' => $thumbnailUrl,
                    'status' => $response->status(),
                    'video_id' => $videoId,
                ]);

                return null;
            }

            // Get content and validate it's an image
            $content = $response->body();
            if (empty($content)) {
                Log::warning('Empty thumbnail content', ['url' => $thumbnailUrl]);

                return null;
            }

            // Determine file extension from content type or URL
            $extension = $this->determineExtension($response->header('Content-Type'), $thumbnailUrl);

            // Generate unique filename
            $filename = $this->generateFilename($videoId, $platform, $extension);

            // Store the thumbnail
            $path = "thumbnails/{$platform}/{$filename}";

            if (! Storage::disk($disk)->put($path, $content)) {
                Log::error('Failed to store thumbnail', [
                    'disk' => $disk,
                    'path' => $path,
                    'video_id' => $videoId,
                ]);

                return null;
            }

            Log::info('Thumbnail downloaded and stored successfully', [
                'disk' => $disk,
                'path' => $path,
                'video_id' => $videoId,
                'size' => strlen($content),
            ]);

            return [
                'disk' => $disk,
                'path' => $path,
            ];

        } catch (ConnectionException $e) {
            Log::warning('Network error downloading thumbnail', [
                'url' => $thumbnailUrl,
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return null;
        } catch (RequestException $e) {
            Log::warning('HTTP error downloading thumbnail', [
                'url' => $thumbnailUrl,
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Unexpected error downloading thumbnail', [
                'url' => $thumbnailUrl,
                'error' => $e->getMessage(),
                'video_id' => $videoId,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Determine file extension from content type or URL.
     */
    private function determineExtension(?string $contentType, string $url): string
    {
        // Try to get extension from content type first
        if ($contentType) {
            $extension = match (strtolower($contentType)) {
                'image/jpeg', 'image/jpg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                default => null,
            };

            if ($extension) {
                return $extension;
            }
        }

        // Fallback to URL extension
        $urlPath = parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

        // Validate extension
        $validExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($extension, $validExtensions)) {
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        // Default to jpg if we can't determine
        return 'jpg';
    }

    /**
     * Generate unique filename for thumbnail.
     */
    private function generateFilename(string $videoId, string $platform, string $extension): string
    {
        // Clean video ID for filename
        $cleanVideoId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $videoId);

        // Generate hash for uniqueness
        $hash = substr(md5($videoId.$platform.time()), 0, 8);

        return "{$cleanVideoId}_{$hash}.{$extension}";
    }

    /**
     * Download thumbnail with streaming for large files.
     */
    public function downloadAndStoreStream(string $thumbnailUrl, string $videoId, string $platform): ?array
    {
        try {
            Log::info('Starting streaming thumbnail download', [
                'url' => $thumbnailUrl,
                'video_id' => $videoId,
                'platform' => $platform,
            ]);

            // Get the configured storage disk
            $disk = config('filesystems.default', 'local');

            // Create a temporary file for streaming
            $tempFile = tempnam(sys_get_temp_dir(), 'thumbnail_');

            // Download with streaming
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])
                ->sink($tempFile)
                ->get($thumbnailUrl);

            if (! $response->successful()) {
                unlink($tempFile);
                Log::warning('Failed to download thumbnail via streaming', [
                    'url' => $thumbnailUrl,
                    'status' => $response->status(),
                    'video_id' => $videoId,
                ]);

                return null;
            }

            // Check file size
            $fileSize = filesize($tempFile);
            if ($fileSize === 0) {
                unlink($tempFile);
                Log::warning('Empty thumbnail file via streaming', ['url' => $thumbnailUrl]);

                return null;
            }

            // Determine file extension
            $extension = $this->determineExtension($response->header('Content-Type'), $thumbnailUrl);

            // Generate unique filename
            $filename = $this->generateFilename($videoId, $platform, $extension);
            $path = "thumbnails/{$platform}/{$filename}";

            // Store the thumbnail using stream
            $stream = fopen($tempFile, 'r');
            $stored = Storage::disk($disk)->writeStream($path, $stream);
            fclose($stream);
            unlink($tempFile);

            if (! $stored) {
                Log::error('Failed to store thumbnail via streaming', [
                    'disk' => $disk,
                    'path' => $path,
                    'video_id' => $videoId,
                ]);

                return null;
            }

            Log::info('Thumbnail downloaded and stored successfully via streaming', [
                'disk' => $disk,
                'path' => $path,
                'video_id' => $videoId,
                'size' => $fileSize,
            ]);

            return [
                'disk' => $disk,
                'path' => $path,
            ];

        } catch (\Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }

            Log::error('Unexpected error downloading thumbnail via streaming', [
                'url' => $thumbnailUrl,
                'error' => $e->getMessage(),
                'video_id' => $videoId,
            ]);

            return null;
        }
    }
}
