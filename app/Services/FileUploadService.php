<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for uploading files to cloud storage with support for large files.
 */
class FileUploadService
{
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
                $this->uploadLargeFile($disk, $localFilePath, $storageFilePath);
            } else {
                $this->uploadSmallFile($disk, $localFilePath, $storageFilePath);
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
    private function uploadSmallFile($disk, string $localFilePath, string $storageFilePath): void
    {
        Log::debug('Uploading small file', [
            'local_file_path' => $localFilePath,
            'storage_file_path' => $storageFilePath,
        ]);

        $fileContents = file_get_contents($localFilePath);
        if ($fileContents === false) {
            throw new Exception("Failed to read local file: {$localFilePath}");
        }

        $disk->put($storageFilePath, $fileContents);
    }

    /**
     * Upload large file using streaming method.
     */
    private function uploadLargeFile($disk, string $localFilePath, string $storageFilePath): void
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
            $disk->writeStream($storageFilePath, $stream);
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
        $uniqueId = uniqid();

        // Sanitize filename
        $sanitizedFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);

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
}
