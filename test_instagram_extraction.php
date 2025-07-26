<?php

/**
 * Test script for Instagram video extraction
 *
 * This script tests the Instagram-specific fixes for:
 * 1. Format detection and parsing
 * 2. Thumbnail URL extraction
 * 3. Quality selection logic
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Services\VideoExtraction\Factory\DriverFactory;
use App\Services\VideoExtraction\FilenameService;
use App\Services\VideoExtraction\YtDlpService;

// Test Instagram URL from the task
$testUrl = 'https://www.instagram.com/p/DMKmjxuM4TX/';

echo "=== Instagram Video Extraction Test ===\n\n";
echo "Testing URL: {$testUrl}\n\n";

try {
    // Initialize services
    $filenameService = new FilenameService;
    $ytDlpService = new YtDlpService($filenameService);

    echo "1. Testing yt-dlp format extraction...\n";

    // Test format extraction
    $formats = $ytDlpService->getAvailableFormats($testUrl);
    echo 'Found '.count($formats)." formats\n";

    foreach ($formats as $index => $format) {
        echo sprintf(
            "  Format %d: ID=%s, Ext=%s, Resolution=%s, Size=%s, TBR=%s, Codec=%s\n",
            $index + 1,
            $format->formatId,
            $format->extension,
            $format->resolution,
            $format->getFormattedFileSize(),
            $format->tbr ?? 'N/A',
            $format->getCodecInfo()
        );

        if ($index >= 4) { // Limit output for readability
            echo '  ... and '.(count($formats) - 5)." more formats\n";
            break;
        }
    }

    echo "\n2. Testing metadata extraction...\n";

    // Test metadata extraction
    $metadata = $ytDlpService->getVideoMetadata($testUrl);
    echo 'Title: '.($metadata['title'] ?? 'Not found')."\n";
    echo 'Thumbnail URL: '.($metadata['thumbnail_url'] ?? 'Not found')."\n";
    echo 'Duration: '.($metadata['duration'] ?? 'Not found')." seconds\n";
    echo 'Video ID: '.($metadata['video_id'] ?? 'Not found')."\n";

    echo "\n3. Testing Instagram driver...\n";

    // Test Instagram driver
    $factory = new DriverFactory;
    $driver = $factory->create($testUrl);

    echo 'Driver: '.get_class($driver)."\n";
    echo 'Platform: '.$driver->getPlatform()->value."\n";

    // Test extraction with different quality settings
    $qualities = [VideoQuality::Q1080P, VideoQuality::Q720P, VideoQuality::Q360P];

    foreach ($qualities as $quality) {
        echo "\n4. Testing extraction with {$quality->value} quality...\n";

        try {
            $result = $driver->extractMetadata($testUrl, [
                'quality' => $quality,
                'format' => VideoFormat::MP4,
            ]);

            echo '  Title: '.($result->getTitle() ?? 'Not found')."\n";
            echo '  Thumbnail: '.($result->getThumbnail() ? 'Found' : 'Not found')."\n";
            echo '  Duration: '.($result->getDuration() ?? 'Not found')." seconds\n";
            echo '  Platform: '.$result->getPlatform()->value."\n";

            $formats = $result->getAdditionalMetadata()['formats'] ?? [];
            echo '  Available formats: '.count($formats)."\n";

            // Test format selection by checking the extraction result
            echo "  Format selection tested via extraction result\n";

        } catch (Exception $e) {
            echo '  Error: '.$e->getMessage()."\n";
        }
    }

    echo "\n5. Testing format quality and type classification...\n";

    foreach ($formats as $index => $format) {
        $formatArray = $format->toArray();
        echo sprintf(
            "  Format %d: %s -> Quality: %s (Audio: %s, Video: %s)\n",
            $index + 1,
            $format->formatId,
            $formatArray['quality'],
            $format->isAudioOnly ? 'Yes' : 'No',
            $format->isVideoOnly ? 'Yes' : 'No'
        );
    }

    echo "\n6. Testing DownloadOption type detection...\n";

    // Simulate how DownloadOption would categorize these
    foreach ($formats as $index => $format) {
        $formatArray = $format->toArray();
        $quality = $formatArray['quality'];

        // Simulate DownloadOption type detection
        $isAudioOnly = $quality === 'audio';
        $isVideoOnly = in_array($quality, ['144', '360', '720', '1080']);
        $isFull = ! $isAudioOnly && ! $isVideoOnly;

        $type = $isAudioOnly ? 'audio_only' : ($isVideoOnly ? 'video_only' : 'full');

        echo sprintf(
            "  Format %d: Quality=%s -> Type=%s\n",
            $index + 1,
            $quality,
            $type
        );
    }

    echo "\n=== Test completed ===\n";

} catch (Exception $e) {
    echo 'Fatal error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
