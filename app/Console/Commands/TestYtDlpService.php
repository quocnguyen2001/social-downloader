<?php

namespace App\Console\Commands;

use App\Services\VideoExtraction\Exceptions\YtDlpException;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Console command to test YtDlpService directly.
 */
class TestYtDlpService extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ytdlp:test {url} {--debug}';

    /**
     * The console command description.
     */
    protected $description = 'Test YtDlpService directly with a URL';

    /**
     * Execute the console command.
     */
    public function handle(YtDlpService $ytDlpService): int
    {
        $url = $this->argument('url');
        $debug = $this->option('debug');

        $this->info("Testing YtDlpService with URL: {$url}");
        $this->newLine();

        // Show configuration
        $this->info('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Binary Path', config('video-extraction.yt_dlp.binary_path', 'yt-dlp')],
                ['Timeout', config('video-extraction.yt_dlp.timeout', 300)],
                ['User Agent', config('video-extraction.yt_dlp.user_agent', 'Not set')],
                ['Instagram Enabled', config('video-extraction.drivers.instagram.enabled', false) ? 'Yes' : 'No'],
            ]
        );
        $this->newLine();

        try {
            // Test getting available formats
            $this->info('Testing getAvailableFormats()...');

            if ($debug) {
                // Enable debug logging temporarily
                Log::info('Debug mode enabled for YtDlpService test');
            }

            $formats = $ytDlpService->getAvailableFormats($url);

            $this->info('✓ Successfully retrieved '.count($formats).' formats');

            if (count($formats) > 0) {
                $this->newLine();
                $this->info('Available formats:');

                $formatData = [];
                foreach (array_slice($formats, 0, 10) as $format) { // Show first 10 formats
                    $formatData[] = [
                        $format->formatId,
                        $format->extension,
                        $format->resolution,
                        $format->filesize ? number_format($format->filesize).' bytes' : 'Unknown',
                        $format->isAudioOnly ? 'Audio' : ($format->isVideoOnly ? 'Video' : 'Combined'),
                    ];
                }

                $this->table(
                    ['Format ID', 'Extension', 'Resolution', 'File Size', 'Type'],
                    $formatData
                );

                if (count($formats) > 10) {
                    $this->info('... and '.(count($formats) - 10).' more formats');
                }
            }

            $this->newLine();

            // Test getting metadata
            $this->info('Testing getVideoMetadata()...');
            $metadata = $ytDlpService->getVideoMetadata($url);

            if (! empty($metadata)) {
                $this->info('✓ Successfully retrieved metadata');
                $this->table(
                    ['Field', 'Value'],
                    array_map(fn ($key, $value) => [$key, is_string($value) ? substr($value, 0, 100) : $value],
                        array_keys($metadata), array_values($metadata))
                );
            } else {
                $this->warn('No metadata retrieved');
            }

            $this->newLine();
            $this->info('✓ YtDlpService test completed successfully!');

            return self::SUCCESS;

        } catch (YtDlpException $e) {
            $this->error('YtDlp error: '.$e->getMessage());
            $this->error('Exit code: '.$e->getExitCode());

            if ($debug) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error('General error: '.$e->getMessage());

            if ($debug) {
                $this->error('Stack trace: '.$e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
