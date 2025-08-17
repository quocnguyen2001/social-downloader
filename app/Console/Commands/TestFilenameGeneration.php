<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\VideoExtraction\FilenameService;
use Illuminate\Console\Command;

/**
 * Command to test the new hash-based filename generation system.
 */
class TestFilenameGeneration extends Command
{
    protected $signature = 'filename:test {url} {cdn_id} {--session_id=} {--no-timestamp}';

    protected $description = 'Test hash-based filename generation for video downloads';

    public function handle(FilenameService $filenameService): int
    {
        $url = $this->argument('url');
        $cdnId = $this->argument('cdn_id');
        $sessionId = $this->option('session_id');
        $includeTimestamp = ! $this->option('no-timestamp');

        $this->info('Testing filename generation...');
        $this->line('');

        // Test stable filename generation
        $stableFilename = $filenameService->generateStableFilename(
            $url,
            $cdnId,
            $sessionId,
            $includeTimestamp
        );

        $this->info('Input Parameters:');
        $this->line("  URL: {$url}");
        $this->line("  CDN ID: {$cdnId}");
        $this->line('  Session ID: '.($sessionId ?: 'none'));
        $this->line('  Include Timestamp: '.($includeTimestamp ? 'yes' : 'no'));
        $this->line('');

        $this->info('Generated Filename:');
        $this->line("  {$stableFilename}");
        $this->line('');

        // Test content hash
        $contentHash = $filenameService->generateContentHash($url, $cdnId);
        $this->info('Content Hash (for duplicate detection):');
        $this->line("  {$contentHash}");
        $this->line('');

        // Test hash extraction
        $extractedHash = $filenameService->extractHashFromFilename($stableFilename);
        $this->info('Extracted Hash:');
        $this->line("  {$extractedHash}");
        $this->line('');

        // Test if filename is hash-based
        $isHashBased = $filenameService->isHashBasedFilename($stableFilename);
        $this->info('Is Hash-Based Filename:');
        $this->line('  '.($isHashBased ? 'yes' : 'no'));
        $this->line('');

        // Test display filename generation
        $metadata = [
            'title' => 'Sample Video Title - Test Video',
            'uploader' => 'TestChannel',
        ];
        $displayFilename = $filenameService->generateDisplayFilename($metadata, 'mp4');
        $this->info('Display Filename (for UI):');
        $this->line("  {$displayFilename}");
        $this->line('');

        // Test consistency - generate same filename multiple times
        $this->info('Testing Consistency (without timestamp):');
        $consistentFilename1 = $filenameService->generateStableFilename($url, $cdnId, $sessionId, false);
        $consistentFilename2 = $filenameService->generateStableFilename($url, $cdnId, $sessionId, false);

        $this->line("  First:  {$consistentFilename1}");
        $this->line("  Second: {$consistentFilename2}");
        $this->line('  Match:  '.($consistentFilename1 === $consistentFilename2 ? 'yes' : 'no'));
        $this->line('');

        // Test with different parameters
        $this->info('Testing with Different Parameters:');
        $differentCdn = $filenameService->generateStableFilename($url, 'different-cdn-id', $sessionId, false);
        $differentUrl = $filenameService->generateStableFilename('https://example.com/different', $cdnId, $sessionId, false);

        $this->line("  Different CDN ID: {$differentCdn}");
        $this->line("  Different URL:    {$differentUrl}");
        $this->line('');

        $this->info('✅ Filename generation test completed successfully!');

        return self::SUCCESS;
    }
}
