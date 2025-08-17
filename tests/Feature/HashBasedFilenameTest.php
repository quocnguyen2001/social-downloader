<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\VideoExtraction\FilenameService;
use Tests\TestCase;

class HashBasedFilenameTest extends TestCase
{
    private FilenameService $filenameService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filenameService = app(FilenameService::class);
    }

    public function test_generates_consistent_hash_for_same_inputs(): void
    {
        $url = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $cdnId = 'best';
        $sessionId = 'test-session';

        $filename1 = $this->filenameService->generateStableFilename($url, $cdnId, $sessionId, false);
        $filename2 = $this->filenameService->generateStableFilename($url, $cdnId, $sessionId, false);

        $this->assertEquals($filename1, $filename2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}\.%\(ext\)s$/', $filename1);
    }

    public function test_generates_different_hash_for_different_inputs(): void
    {
        $url1 = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $url2 = 'https://www.facebook.com/share/v/157e6ozkb2/';
        $cdnId = 'best';

        $filename1 = $this->filenameService->generateStableFilename($url1, $cdnId, null, false);
        $filename2 = $this->filenameService->generateStableFilename($url2, $cdnId, null, false);

        $this->assertNotEquals($filename1, $filename2);
    }

    public function test_includes_timestamp_when_requested(): void
    {
        $url = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $cdnId = 'best';

        $filename = $this->filenameService->generateStableFilename($url, $cdnId, null, true);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}_\d{8}_\d{6}\.%\(ext\)s$/', $filename);
    }

    public function test_generates_content_hash_for_duplicate_detection(): void
    {
        $url = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $cdnId = 'best';

        $hash1 = $this->filenameService->generateContentHash($url, $cdnId);
        $hash2 = $this->filenameService->generateContentHash($url, $cdnId);

        $this->assertEquals($hash1, $hash2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $hash1);
    }

    public function test_extracts_hash_from_filename(): void
    {
        $url = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $cdnId = 'best';

        $filename = $this->filenameService->generateStableFilename($url, $cdnId, null, true);
        $extractedHash = $this->filenameService->extractHashFromFilename($filename);

        $this->assertNotNull($extractedHash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $extractedHash);
    }

    public function test_detects_hash_based_filenames(): void
    {
        $hashBasedFilename = 'd1839ff7b9f8b2f4_20250725_191549.mp4';
        $legacyFilename = 'Some Video Title.mp4';

        $this->assertTrue($this->filenameService->isHashBasedFilename($hashBasedFilename));
        $this->assertFalse($this->filenameService->isHashBasedFilename($legacyFilename));
    }

    public function test_generates_display_filename(): void
    {
        $metadata = [
            'title' => 'Amazing Video Content',
            'uploader' => 'TestChannel',
        ];

        $displayFilename = $this->filenameService->generateDisplayFilename($metadata, 'mp4');

        $this->assertStringContainsString('TestChannel', $displayFilename);
        $this->assertStringContainsString('Amazing_Video_Content', $displayFilename);
        $this->assertStringEndsWith('.mp4', $displayFilename);
    }

    public function test_normalizes_urls_consistently(): void
    {
        // URLs with different query parameters should normalize to same hash
        $url1 = 'https://www.instagram.com/p/DMKmjxuM4TX/?utm_source=test';
        $url2 = 'https://www.instagram.com/p/DMKmjxuM4TX/?utm_campaign=different';
        $url3 = 'https://www.instagram.com/p/DMKmjxuM4TX/';

        $hash1 = $this->filenameService->generateContentHash($url1, 'best');
        $hash2 = $this->filenameService->generateContentHash($url2, 'best');
        $hash3 = $this->filenameService->generateContentHash($url3, 'best');

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals($hash2, $hash3);
    }

    public function test_handles_session_id_in_hash_generation(): void
    {
        $url = 'https://www.instagram.com/p/DMKmjxuM4TX/';
        $cdnId = 'best';

        $hashWithoutSession = $this->filenameService->generateContentHash($url, $cdnId);

        $filenameWithSession = $this->filenameService->generateStableFilename($url, $cdnId, 'session-123', false);
        $filenameWithoutSession = $this->filenameService->generateStableFilename($url, $cdnId, null, false);

        // Should be different when session ID is included
        $this->assertNotEquals($filenameWithSession, $filenameWithoutSession);
    }

    public function test_sanitizes_display_filenames(): void
    {
        $metadata = [
            'title' => 'Video with "quotes" and /slashes/ and <tags>',
            'uploader' => 'Channel@Name!',
        ];

        $displayFilename = $this->filenameService->generateDisplayFilename($metadata, 'mp4');

        // Should not contain problematic characters
        $this->assertStringNotContainsString('"', $displayFilename);
        $this->assertStringNotContainsString('/', $displayFilename);
        $this->assertStringNotContainsString('<', $displayFilename);
        $this->assertStringNotContainsString('>', $displayFilename);
        $this->assertStringNotContainsString('@', $displayFilename);
        $this->assertStringNotContainsString('!', $displayFilename);
    }
}
