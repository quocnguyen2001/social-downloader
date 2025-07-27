<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FileUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestFileUploadHeaders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:file-upload-headers {filename?} {disk?}';

    /**
     * The console command description.
     */
    protected $description = 'Test file upload with headers to S3/R2 storage';

    /**
     * Execute the console command.
     */
    public function handle(FileUploadService $fileUploadService): int
    {
        $filename = $this->argument('filename') ?? 'test-video.mp4';
        $disk = $this->argument('disk') ?? config('video-extraction.upload.default_storage_disk', 'r2');

        $this->info('Testing file upload headers...');
        $this->info("Filename: {$filename}");
        $this->info("Disk: {$disk}");

        // Create a temporary test file
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, 'This is a test file for header verification.');

        try {
            $this->info('Uploading file...');

            $result = $fileUploadService->uploadFile(
                $tempFile,
                $filename,
                $disk,
                true // Delete local file after upload
            );

            $this->info('Upload successful!');
            $this->table(['Key', 'Value'], [
                ['Storage Disk', $result['storage_disk']],
                ['Storage Path', $result['storage_file_path']],
                ['File Size', $result['file_size'].' bytes'],
            ]);

            // Try to get file metadata if possible
            $this->info("\nAttempting to retrieve file metadata...");

            try {
                $diskInstance = Storage::disk($disk);
                $mimeType = $diskInstance->mimeType($result['storage_file_path']);
                $this->info("Detected MIME Type: {$mimeType}");

                // Generate download URL for testing
                $url = $diskInstance->url($result['storage_file_path']);
                $this->info("Download URL: {$url}");

                $this->info("\n✅ Test completed successfully!");
                $this->info('Please test the download URL with curl to verify headers:');
                $this->info("curl -I \"{$url}\"");
                $this->info("\nExpected headers:");
                $this->info('- Content-Type: Should match the file extension');
                $this->info("- Content-Disposition: Should be 'attachment; filename=\"{$filename}\"'");

            } catch (\Exception $e) {
                $this->warn('Could not retrieve metadata: '.$e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error('Upload failed: '.$e->getMessage());

            // Clean up temp file if it still exists
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return 1;
        }

        return 0;
    }
}
