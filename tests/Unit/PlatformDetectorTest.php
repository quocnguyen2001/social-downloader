<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Platform;
use App\Services\VideoExtraction\PlatformDetector;
use Tests\TestCase;

class PlatformDetectorTest extends TestCase
{
    /**
     * @dataProvider detectionDataProvider
     */
    public function test_detect_platform(string $url, ?Platform $expectedPlatform): void
    {
        $detector = new PlatformDetector();

        $this->assertSame($expectedPlatform, $detector->detectPlatform($url));
    }

    public static function detectionDataProvider(): array
    {
        return [
            'facebook reel' => ['https://www.facebook.com/reel/824073223812116', Platform::FACEBOOK],
            'facebook reels short link' => ['facebook.com/reels/1234/permalink', Platform::FACEBOOK],
            'facebook watch' => ['https://fb.watch/abc123', Platform::FACEBOOK],
            'facebook story' => ['https://m.facebook.com/story.php?story_fbid=1&id=2', Platform::FACEBOOK],
            'youtube watch' => ['https://www.youtube.com/watch?v=abc123', Platform::YOUTUBE],
            'youtube shorts' => ['https://youtube.com/shorts/abc123', Platform::YOUTUBE],
            'youtube short url' => ['https://youtu.be/xyz987', Platform::YOUTUBE],
            'youtube nocookie' => ['https://www.youtube-nocookie.com/embed/video-id', Platform::YOUTUBE],
            'tiktok video' => ['https://www.tiktok.com/@creator/video/123', Platform::TIKTOK],
            'tiktok short share' => ['https://vt.tiktok.com/ZTRJt/', Platform::TIKTOK],
            'instagram reel' => ['https://www.instagram.com/reel/CrV', Platform::INSTAGRAM],
            'instagram short domain' => ['https://instagr.am/p/ABC123', Platform::INSTAGRAM],
            'unsupported url' => ['https://example.com/video', null],
            'blank string' => ['', null],
        ];
    }

    public function test_is_supported(): void
    {
        $detector = new PlatformDetector();

        $this->assertTrue($detector->isSupported('https://www.facebook.com/reel/824073223812116'));
        $this->assertFalse($detector->isSupported('https://example.org/watch?v=1'));
    }
}
