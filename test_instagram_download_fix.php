<?php

/**
 * Test script for Instagram download format selector fix
 */

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Models\DownloadOption;
use App\Models\DownloadSession;
use App\Services\VideoExtraction\FilenameService;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Support\Str;

echo "=== Instagram Download Format Selector Test ===\n\n";

try {
    // Create a test download session
    $testUrl = 'https://www.instagram.com/p/DMKmjxuM4TX/';

    $downloadSession = DownloadSession::create([
        'id' => Str::uuid(),
        'original_url' => $testUrl,
        'platform' => Platform::INSTAGRAM,
        'status' => DownloadSessionStatus::METADATA_FETCHED,
        'api_key_id' => null, // For testing
    ]);

    echo "Created test download session: {$downloadSession->id}\n";

    // Create test download options
    $testOptions = [
        ['quality' => 'audio', 'cdn_id' => 'dash-773100965165713ad'],
        ['quality' => '360', 'cdn_id' => 'dash-1723013591753938v'],
        ['quality' => '720', 'cdn_id' => 'dash-736062299414970vd'],
        ['quality' => '1080', 'cdn_id' => '3'],
    ];

    foreach ($testOptions as $optionData) {
        DownloadOption::create([
            'id' => Str::uuid(),
            'download_session_id' => $downloadSession->id,
            'cdn_id' => $optionData['cdn_id'],
            'quality' => $optionData['quality'],
            'mime_type' => $optionData['quality'] === 'audio' ? 'audio/mp4' : 'video/mp4',
            'status' => 'cdn',
        ]);

        echo "Created download option: {$optionData['quality']} -> {$optionData['cdn_id']}\n";
    }

    echo "\n=== Testing Format Selector Logic ===\n";

    // Initialize YtDlpService
    $filenameService = new FilenameService;
    $ytDlpService = new YtDlpService($filenameService);

    // Use reflection to test the private buildFormatString method
    $reflection = new ReflectionClass($ytDlpService);
    $buildFormatStringMethod = $reflection->getMethod('buildFormatString');
    $buildFormatStringMethod->setAccessible(true);

    // Test each format
    foreach ($testOptions as $optionData) {
        $formatString = $buildFormatStringMethod->invoke(
            $ytDlpService,
            $optionData['cdn_id'],
            $downloadSession->id,
            $testUrl
        );

        echo sprintf(
            "Quality: %s, Original CDN ID: %s -> Format String: %s\n",
            $optionData['quality'],
            $optionData['cdn_id'],
            $formatString
        );
    }

    echo "\n=== Testing Non-Instagram URL (should use original format) ===\n";

    $youtubeUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    $formatString = $buildFormatStringMethod->invoke(
        $ytDlpService,
        'test-format-id',
        null,
        $youtubeUrl
    );

    echo "YouTube URL -> Format String: {$formatString}\n";

    // Cleanup
    echo "\n=== Cleaning up test data ===\n";
    $downloadSession->downloadOptions()->delete();
    $downloadSession->delete();
    echo "Test data cleaned up successfully\n";

    echo "\n=== Test completed successfully ===\n";

} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";

    // Cleanup on error
    if (isset($downloadSession)) {
        try {
            $downloadSession->downloadOptions()->delete();
            $downloadSession->delete();
            echo "Cleaned up test data after error\n";
        } catch (Exception $cleanupError) {
            echo 'Cleanup error: '.$cleanupError->getMessage()."\n";
        }
    }
}
