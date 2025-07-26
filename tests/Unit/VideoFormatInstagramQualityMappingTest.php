<?php

namespace Tests\Unit;

use App\Services\VideoExtraction\DTOs\VideoFormat;
use PHPUnit\Framework\TestCase;

/**
 * Test Instagram video format quality mapping functionality.
 */
class VideoFormatInstagramQualityMappingTest extends TestCase
{
    /**
     * Test that Instagram portrait video resolutions are correctly mapped to standard qualities.
     */
    public function test_instagram_portrait_video_quality_mapping(): void
    {
        // Test 1080p Instagram video (1080x1920)
        $format1080 = new VideoFormat(
            formatId: 'test-1080',
            extension: 'mp4',
            resolution: '1080x1920',
            vcodec: 'vp09.00.40.08',
            acodec: 'none',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $result1080 = $format1080->toArray();
        $this->assertEquals('1080', $result1080['quality'], 'Instagram 1080x1920 should map to 1080p quality');

        // Test 720p Instagram video (720x1280)
        $format720 = new VideoFormat(
            formatId: 'test-720',
            extension: 'mp4',
            resolution: '720x1280',
            vcodec: 'vp09.00.31.08',
            acodec: 'none',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $result720 = $format720->toArray();
        $this->assertEquals('720', $result720['quality'], 'Instagram 720x1280 should map to 720p quality');

        // Test 360p Instagram video (480x720)
        $format360 = new VideoFormat(
            formatId: 'test-360',
            extension: 'mp4',
            resolution: '480x720',
            vcodec: 'h264',
            acodec: 'none',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $result360 = $format360->toArray();
        $this->assertEquals('360', $result360['quality'], 'Instagram 480x720 should map to 360p quality');

        // Test audio-only format
        $formatAudio = new VideoFormat(
            formatId: 'test-audio',
            extension: 'm4a',
            resolution: 'audio only',
            vcodec: 'none',
            acodec: 'mp4a.40.5',
            isVideoOnly: false,
            isAudioOnly: true
        );

        $resultAudio = $formatAudio->toArray();
        $this->assertEquals('audio', $resultAudio['quality'], 'Audio-only format should map to audio quality');
    }

    /**
     * Test edge cases and fallback behavior.
     */
    public function test_instagram_quality_mapping_edge_cases(): void
    {
        // Test very high resolution (should map to 1080)
        $formatHigh = new VideoFormat(
            formatId: 'test-high',
            extension: 'mp4',
            resolution: '1440x2560',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $resultHigh = $formatHigh->toArray();
        $this->assertEquals('1080', $resultHigh['quality'], 'Very high resolution should map to 1080p');

        // Test very low resolution (should map to 144)
        $formatLow = new VideoFormat(
            formatId: 'test-low',
            extension: 'mp4',
            resolution: '240x320',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $resultLow = $formatLow->toArray();
        $this->assertEquals('144', $resultLow['quality'], 'Very low resolution should map to 144p');
    }

    /**
     * Test that video formats are properly distinguished from audio formats.
     */
    public function test_video_audio_format_distinction(): void
    {
        // Video format should not be audio
        $videoFormat = new VideoFormat(
            formatId: 'video-test',
            extension: 'mp4',
            resolution: '720x1280',
            vcodec: 'vp09',
            acodec: 'none',
            isVideoOnly: true,
            isAudioOnly: false
        );

        $videoResult = $videoFormat->toArray();
        $this->assertNotEquals('audio', $videoResult['quality'], 'Video format should not have audio quality');
        $this->assertEquals('720', $videoResult['quality'], 'Video format should have numeric quality');

        // Audio format should be audio
        $audioFormat = new VideoFormat(
            formatId: 'audio-test',
            extension: 'm4a',
            resolution: 'audio only',
            vcodec: 'none',
            acodec: 'mp4a.40.5',
            isVideoOnly: false,
            isAudioOnly: true
        );

        $audioResult = $audioFormat->toArray();
        $this->assertEquals('audio', $audioResult['quality'], 'Audio format should have audio quality');
    }
}
