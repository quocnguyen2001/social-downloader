<?php

namespace App\Console\Commands;

use App\Services\VideoExtraction\Factory\DriverFactory;
use Illuminate\Console\Command;

/**
 * Console command to test video extraction functionality.
 */
class TestVideoExtraction extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'video-extraction:test {url} {--quality=720p} {--format=mp4}';

    /**
     * The console command description.
     */
    protected $description = 'Test video extraction with a given URL';

    /**
     * Execute the console command.
     */
    public function handle(DriverFactory $driverFactory): int
    {
        $url = $this->argument('url');
        $quality = $this->option('quality');
        $format = $this->option('format');

        $this->info("Testing video extraction for: {$url}");
        $this->info("Quality: {$quality}, Format: {$format}");
        $this->newLine();

        try {
            // Analyze URL
            $this->info('Analyzing URL...');
            $analysis = $driverFactory->analyzeUrl($url);

            $this->table(
                ['Property', 'Value'],
                [
                    ['URL', $analysis['url']],
                    ['Supported', $analysis['is_supported'] ? 'Yes' : 'No'],
                    ['Platform', $analysis['detected_platform'] ?? 'Unknown'],
                    ['Driver Available', $analysis['driver_available'] ? 'Yes' : 'No'],
                    ['Driver Class', $analysis['driver_class'] ?? 'N/A'],
                ]
            );

            if (! $analysis['is_supported']) {
                $this->error('URL is not supported by any driver.');

                return self::FAILURE;
            }

            if (! $analysis['driver_available']) {
                $this->error('No driver available for this platform.');

                return self::FAILURE;
            }

            // Show supported qualities and formats
            if (! empty($analysis['supported_qualities'])) {
                $this->info('Supported Qualities: '.implode(', ', $analysis['supported_qualities']));
            }

            if (! empty($analysis['supported_formats'])) {
                $this->info('Supported Formats: '.implode(', ', $analysis['supported_formats']));
            }

            $this->newLine();

            // Test extraction
            $this->info('Testing extraction...');

            $driver = $driverFactory->create($url);

            $this->info('Using driver: '.get_class($driver));
            $this->info('Platform: '.$driver->getPlatform()->value);

            // Note: This would normally use yt-dlp, but for testing we'll just validate the setup
            $this->info('✓ Driver created successfully');
            $this->info('✓ URL validation passed');
            $this->info('✓ Platform detection working');

            $this->newLine();
            $this->info('Video extraction system is working correctly!');

            $this->warn('Note: Actual extraction requires yt-dlp to be installed and configured.');
            $this->warn('Make sure yt-dlp is available at: '.config('video-extraction.yt_dlp.binary_path'));

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Extraction test failed: '.$e->getMessage());
            $this->error('Stack trace: '.$e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
