<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\VideoExtraction\FilenameService;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for uploading files to cloud storage with support for large files.
 */
class FileUploadService
{
    protected FilenameService $filenameService;

    public function __construct(FilenameService $filenameService)
    {
        $this->filenameService = $filenameService;
    }

    /**
     * Upload a file to cloud storage.
     *
     * @param  string  $localFilePath  Path to the local file
     * @param  string  $fileName  Desired filename in storage
     * @param  string|null  $storageDisk  Storage disk to use (defaults to configured default)
     * @param  bool  $deleteLocalFile  Whether to delete local file after successful upload
     * @return array Upload result with storage_disk and storage_file_path
     *
     * @throws Exception
     */
    public function uploadFile(
        string $localFilePath,
        string $fileName,
        ?string $storageDisk = null,
        bool $deleteLocalFile = true
    ): array {
        try {
            // Use default storage disk if none specified
            $storageDisk = $storageDisk ?? config('filesystems.default');

            // Validate local file exists
            if (! file_exists($localFilePath)) {
                throw new Exception("Local file not found: {$localFilePath}");
            }

            $fileSize = filesize($localFilePath);

            Log::info('Starting file upload', [
                'local_file_path' => $localFilePath,
                'file_name' => $fileName,
                'storage_disk' => $storageDisk,
                'file_size' => $fileSize,
            ]);

            // Generate storage path with date-based directory structure
            $storageFilePath = $this->generateStoragePath($fileName);

            // Get storage disk instance
            $disk = Storage::disk($storageDisk);

            // For large files, use streaming upload
            if ($fileSize > $this->getLargeFileThreshold()) {
                $this->uploadLargeFile($disk, $localFilePath, $storageFilePath, $fileName);
            } else {
                $this->uploadSmallFile($disk, $localFilePath, $storageFilePath, $fileName);
            }

            // Verify upload was successful
            if (! $disk->exists($storageFilePath)) {
                throw new Exception("File upload verification failed: {$storageFilePath}");
            }

            $uploadedFileSize = $disk->size($storageFilePath);

            Log::info('File upload completed successfully', [
                'storage_disk' => $storageDisk,
                'storage_file_path' => $storageFilePath,
                'original_size' => $fileSize,
                'uploaded_size' => $uploadedFileSize,
            ]);

            // Clean up local file if requested
            if ($deleteLocalFile) {
                $this->cleanupLocalFile($localFilePath);
            }

            return [
                'storage_disk' => $storageDisk,
                'storage_file_path' => $storageFilePath,
                'file_size' => $uploadedFileSize,
            ];

        } catch (Exception $e) {
            Log::error('File upload failed', [
                'local_file_path' => $localFilePath,
                'file_name' => $fileName,
                'storage_disk' => $storageDisk,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new Exception("Failed to upload file: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Upload small file using standard method.
     */
    private function uploadSmallFile($disk, string $localFilePath, string $storageFilePath, string $fileName): void
    {
        Log::debug('Uploading small file', [
            'local_file_path' => $localFilePath,
            'storage_file_path' => $storageFilePath,
        ]);

        $fileContents = file_get_contents($localFilePath);
        if ($fileContents === false) {
            throw new Exception("Failed to read local file: {$localFilePath}");
        }

        // Get disk name for metadata handling
        $diskName = $this->getDiskName($disk);

        // Upload with metadata for S3-compatible disks
        if ($this->isS3CompatibleDisk($diskName)) {
            $this->uploadWithMetadata($disk, $storageFilePath, $fileContents, $fileName);
        } else {
            $disk->put($storageFilePath, $fileContents);
        }
    }

    /**
     * Upload large file using streaming method.
     */
    private function uploadLargeFile($disk, string $localFilePath, string $storageFilePath, string $fileName): void
    {
        Log::debug('Uploading large file with streaming', [
            'local_file_path' => $localFilePath,
            'storage_file_path' => $storageFilePath,
        ]);

        $stream = fopen($localFilePath, 'r');
        if ($stream === false) {
            throw new Exception("Failed to open local file for streaming: {$localFilePath}");
        }

        try {
            // Get disk name for metadata handling
            $diskName = $this->getDiskName($disk);

            // Upload with metadata for S3-compatible disks
            if ($this->isS3CompatibleDisk($diskName)) {
                $this->uploadStreamWithMetadata($disk, $storageFilePath, $stream, $fileName);
            } else {
                $disk->writeStream($storageFilePath, $stream);
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Generate storage path with date-based directory structure.
     */
    private function generateStoragePath(string $fileName): string
    {
        $date = now()->format('Y/m/d');

        // Sanitize filename - preserve hash-based names from yt-dlp
        $sanitizedFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

        // If filename is hash-based from our FilenameService, don't add unique ID
        if ($this->filenameService->isHashBasedFilename($sanitizedFileName)) {
            return "downloads/{$date}/{$sanitizedFileName}";
        }

        // For non-hash filenames, add unique ID for safety
        $uniqueId = uniqid();

        return "downloads/{$date}/{$uniqueId}_{$sanitizedFileName}";
    }

    /**
     * Get threshold for large file uploads (in bytes).
     */
    private function getLargeFileThreshold(): int
    {
        return config('video-extraction.upload.large_file_threshold', 100 * 1024 * 1024); // 100MB
    }

    /**
     * Clean up local file after successful upload.
     */
    private function cleanupLocalFile(string $localFilePath): void
    {
        try {
            if (file_exists($localFilePath)) {
                unlink($localFilePath);
                Log::debug('Local file cleaned up', ['file_path' => $localFilePath]);
            }
        } catch (Exception $e) {
            Log::warning('Failed to cleanup local file', [
                'file_path' => $localFilePath,
                'error' => $e->getMessage(),
            ]);
            // Don't throw exception for cleanup failures
        }
    }

    /**
     * Get available storage disks.
     */
    public function getAvailableDisks(): array
    {
        return array_keys(config('filesystems.disks'));
    }

    /**
     * Check if a storage disk is available.
     */
    public function isDiskAvailable(string $disk): bool
    {
        return in_array($disk, $this->getAvailableDisks());
    }

    /**
     * Get disk name from disk instance.
     */
    private function getDiskName($disk): string
    {
        // Try to get disk name from config by comparing instances
        foreach (config('filesystems.disks') as $name => $config) {
            if (Storage::disk($name) === $disk) {
                return $name;
            }
        }

        // Fallback: check if it's a known S3-compatible disk
        $diskConfig = $disk->getConfig();
        if (isset($diskConfig['driver']) && $diskConfig['driver'] === 's3') {
            return 's3'; // Generic S3 disk
        }

        return 'local'; // Default fallback
    }

    /**
     * Check if disk is S3-compatible (S3 or R2).
     */
    private function isS3CompatibleDisk(string $diskName): bool
    {
        $diskConfig = config("filesystems.disks.{$diskName}");

        return isset($diskConfig['driver']) && $diskConfig['driver'] === 's3';
    }

    /**
     * Get MIME type from file extension.
     */
    private function getMimeTypeFromExtension(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $mimeTypes = [
            // Video formats
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            'flv' => 'video/x-flv',
            'wmv' => 'video/x-ms-wmv',
            'm4v' => 'video/x-m4v',
            '3gp' => 'video/3gpp',
            'ogv' => 'video/ogg',

            // Audio formats
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/x-m4a',
            'wma' => 'audio/x-ms-wma',
            'opus' => 'audio/opus',

            // Image formats
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',

            // Document formats
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Sanitize filename for Content-Disposition header.
     */
    private function sanitizeFilenameForDisposition(string $fileName): string
    {
        // Remove or replace invalid characters for Content-Disposition
        $sanitized = preg_replace('/[<>:"|?*\\\\/]/', '_', $fileName);

        // Remove control characters
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $sanitized);

        // Limit length while preserving extension
        $maxLength = 200;
        if (strlen($sanitized) > $maxLength) {
            $extension = pathinfo($sanitized, PATHINFO_EXTENSION);
            $basename = pathinfo($sanitized, PATHINFO_FILENAME);
            $basename = substr($basename, 0, $maxLength - strlen($extension) - 1);
            $sanitized = $basename.'.'.$extension;
        }

        // Ensure we have a valid filename
        if (empty(trim($sanitized, '.'))) {
            $sanitized = 'download';
        }

        return $sanitized;
    }

    /**
     * Generate Content-Disposition header value.
     */
    private function generateContentDisposition(string $fileName): string
    {
        $sanitizedName = $this->sanitizeFilenameForDisposition($fileName);

        return 'attachment; filename="'.$sanitizedName.'"';
    }

    /**
     * Get upload metadata for S3-compatible disks.
     */
    private function getUploadMetadata(string $fileName): array
    {
        $metadata = [
            'Content-Type' => $this->getMimeTypeFromExtension($fileName),
            'Content-Disposition' => $this->generateContentDisposition($fileName),
        ];

        Log::debug('Generated upload metadata', [
            'filename' => $fileName,
            'metadata' => $metadata,
        ]);

        return $metadata;
    }

    /**
     * Upload file content with metadata to S3-compatible disk.
     */
    private function uploadWithMetadata($disk, string $storageFilePath, string $fileContents, string $fileName): void
    {
        try {
            $metadata = $this->getUploadMetadata($fileName);

            Log::debug('Uploading file with metadata', [
                'storage_file_path' => $storageFilePath,
                'metadata' => $metadata,
            ]);

            // Get the S3 client directly for proper header setting
            $adapter = $disk->getAdapter();
            $client = $adapter->getClient();
            $config = $disk->getConfig();

            // Use S3 client putObject with proper parameters
            $result = $client->putObject([
                'Bucket' => $config['bucket'],
                'Key' => $storageFilePath,
                'Body' => $fileContents,
                'Content-Type' => $metadata['ContentType'],
                'Content-Disposition' => $metadata['ContentDisposition'],
            ]);

            Log::debug('Successfully uploaded with S3 client putObject', [
                'etag' => $result['ETag'] ?? 'unknown',
                'content_type' => $metadata['ContentType'],
                'content_disposition' => $metadata['ContentDisposition'],
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to upload with metadata, falling back to standard upload', [
                'storage_file_path' => $storageFilePath,
                'error' => $e->getMessage(),
            ]);

            // Fallback to standard upload without metadata
            $disk->put($storageFilePath, $fileContents);
        }
    }

    /**
     * Upload file stream with metadata to S3-compatible disk.
     */
    private function uploadStreamWithMetadata($disk, string $storageFilePath, $stream, string $fileName): void
    {
        try {
            $metadata = $this->getUploadMetadata($fileName);

            Log::debug('Uploading stream with metadata', [
                'storage_file_path' => $storageFilePath,
                'metadata' => $metadata,
            ]);

            // Get the S3 client directly for proper header setting
            $adapter = $disk->getAdapter();
            $client = $adapter->getClient();
            $config = $disk->getConfig();

            // Use S3 client putObject with stream
            $result = $client->putObject([
                'Bucket' => $config['bucket'],
                'Key' => $storageFilePath,
                'Body' => $stream,
                'Content-Type' => $metadata['ContentType'],
                'Content-Disposition' => $metadata['ContentDisposition'],
            ]);

            Log::debug('Successfully uploaded stream with S3 client putObject', [
                'etag' => $result['ETag'] ?? 'unknown',
                'content_type' => $metadata['ContentType'],
                'content_disposition' => $metadata['ContentDisposition'],
            ]);

        } catch (Exception $e) {
            Log::warning('Failed to upload stream with metadata, falling back to standard upload', [
                'storage_file_path' => $storageFilePath,
                'error' => $e->getMessage(),
            ]);

            // Fallback to standard stream upload without metadata
            $disk->writeStream($storageFilePath, $stream);
        }
    }
}
