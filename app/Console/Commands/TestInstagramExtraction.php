<?php

namespace App\Console\Commands;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Jobs\ExtractVideoMetadataJob;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Console command to test Instagram extraction with the specific URL from the task.
 */
class TestInstagramExtraction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'instagram:test {--url=https://www.instagram.com/p/DMKmjxuM4TX/} {--job : Test via job instead of direct service}';

    /**
     * The console command description.
     */
    protected $description = 'Test Instagram extraction with the specific URL from the task';

    /**
     * Execute the console command.
     */
    public function handle(YtDlpService $ytDlpService): int
    {
        $url = $this->option('url');
        $useJob = $this->option('job');

        $this->info("Testing Instagram extraction with URL: {$url}");
        $this->newLine();

        if ($useJob) {
            return $this->testViaJob($url);
        } else {
            return $this->testDirectService($url, $ytDlpService);
        }
    }

    /**
     * Test via direct service call.
     */
    private function testDirectService(string $url, YtDlpService $ytDlpService): int
    {
        $this->info('Testing via direct YtDlpService call...');

        try {
            // Test the service directly
            $this->info('Step 1: Getting available formats...');
            $formats = $ytDlpService->getAvailableFormats($url);

            $this->info('✓ Retrieved '.count($formats).' formats');

            if (count($formats) > 0) {
                $this->table(
                    ['Format ID', 'Extension', 'Resolution', 'File Size', 'Type'],
                    array_map(fn ($format) => [
                        $format->formatId,
                        $format->extension,
                        $format->resolution,
                        $format->filesize ? number_format($format->filesize).' bytes' : 'Unknown',
                        $format->isAudioOnly ? 'Audio' : ($format->isVideoOnly ? 'Video' : 'Combined'),
                    ], array_slice($formats, 0, 10))
                );
            }

            $this->newLine();
            $this->info('Step 2: Getting video metadata...');
            $metadata = $ytDlpService->getVideoMetadata($url);

            if (! empty($metadata)) {
                $this->info('✓ Retrieved metadata');
                $this->table(
                    ['Field', 'Value'],
                    array_map(fn ($key, $value) => [$key, is_string($value) ? substr($value, 0, 100) : $value],
                        array_keys($metadata), array_values($metadata))
                );
            }

            $this->newLine();
            $this->info('✓ Direct service test completed successfully!');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Direct service test failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Test via job execution.
     */
    private function testViaJob(string $url): int
    {
        $this->info('Testing via ExtractVideoMetadataJob...');

        try {
            // Create a test download session
            $downloadSession = DownloadSession::create([
                'id' => Str::uuid(),
                'original_url' => $url,
                'platform' => Platform::INSTAGRAM,
                'status' => DownloadSessionStatus::PENDING,
            ]);

            $this->info("Created test download session: {$downloadSession->id}");

            // Dispatch the job synchronously
            $job = new ExtractVideoMetadataJob($downloadSession->id);
            $job->handle(app(YtDlpService::class));

            // Refresh the session to see results
            $downloadSession->refresh();

            $this->info("Job completed. Session status: {$downloadSession->status->value}");

            if ($downloadSession->status === DownloadSessionStatus::METADATA_FETCHED) {
                $this->info('✓ Job completed successfully!');

                $optionsCount = $downloadSession->downloadOptions()->count();
                $this->info("Created {$optionsCount} download options");

                if ($optionsCount > 0) {
                    $options = $downloadSession->downloadOptions()->get();
                    $this->table(
                        ['Quality', 'MIME Type', 'File Size', 'Status'],
                        $options->map(fn ($option) => [
                            $option->quality,
                            $option->mime_type,
                            $option->file_size ? number_format($option->file_size).' bytes' : 'Unknown',
                            $option->status->value,
                        ])->toArray()
                    );
                }

                return self::SUCCESS;
            } else {
                $this->error("Job failed. Session status: {$downloadSession->status->value}");
                if ($downloadSession->error_message) {
                    $this->error("Error: {$downloadSession->error_message}");
                }

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('Job test failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
