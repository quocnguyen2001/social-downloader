<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DownloadOption;
use Illuminate\Support\Facades\Storage;

/**
 * Service for generating download URLs.
 * Handles both direct storage downloads and R2 Worker downloads.
 */
class DownloadUrlService
{
    /**
     * Get the download URL for a DownloadOption.
     * If R2_WORKER_DOWNLOAD_URL is configured, use that; otherwise use Storage facade.
     */
    public function getDownloadUrl(DownloadOption $downloadOption): ?string
    {
        // Check if file is downloaded locally
        if ($downloadOption->status === \App\Enums\DownloadOptionStatus::DOWNLOADED
            && $downloadOption->storage_disk
            && $downloadOption->storage_file_path) {

            $workerUrl = config('filesystems.r2_worker_download_url');

            // If R2 Worker URL is configured, use it
            if ($workerUrl && $downloadOption->storage_disk === 'r2') {
                return $this->buildWorkerUrl($workerUrl, $downloadOption->storage_file_path);
            }

            // Otherwise, use Storage facade to generate URL
            return Storage::disk($downloadOption->storage_disk)->url($downloadOption->storage_file_path);
        }

        // If content is on CDN, return CDN URL
        if ($downloadOption->status === \App\Enums\DownloadOptionStatus::CDN
            && $downloadOption->download_cdn_url) {
            return $downloadOption->download_cdn_url;
        }

        return null;
    }

    /**
     * Get a download response for a DownloadOption.
     * If R2_WORKER_DOWNLOAD_URL is configured, redirect to worker; otherwise stream from storage.
     */
    public function getDownloadResponse(DownloadOption $downloadOption): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $workerUrl = config('filesystems.r2_worker_download_url');

        // If R2 Worker URL is configured and file is on R2, redirect to worker
        if ($workerUrl && $downloadOption->storage_disk === 'r2' && $downloadOption->storage_file_path) {
            $downloadUrl = $this->buildWorkerUrl($workerUrl, $downloadOption->storage_file_path);

            return redirect($downloadUrl);
        }

        // Otherwise, use Storage facade to download directly
        return Storage::disk($downloadOption->storage_disk)->download($downloadOption->storage_file_path);
    }

    /**
     * Build the worker URL by appending the file path.
     */
    private function buildWorkerUrl(string $workerBaseUrl, string $filePath): string
    {
        // Remove trailing slash from base URL and leading slash from file path
        $workerBaseUrl = rtrim($workerBaseUrl, '/');
        $filePath = ltrim($filePath, '/');

        return $workerBaseUrl.'/'.$filePath;
    }
}
