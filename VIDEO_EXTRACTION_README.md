# Video Extraction System

This document describes the video extraction system implemented for the social-downloader project. The system uses a driver pattern architecture with yt-dlp as the underlying extraction tool.

## Architecture Overview

The video extraction system follows a modular driver pattern design that allows for easy extension and maintenance:

### Core Components

1. **Driver Pattern**: Each platform (YouTube, TikTok, Instagram, Facebook) has its own driver
2. **Factory Pattern**: Automatic platform detection and driver instantiation
3. **Registry Pattern**: Dynamic driver registration and management
4. **Event-Driven Jobs**: Asynchronous processing with Laravel queues
5. **yt-dlp Integration**: Uses yt-dlp for actual video extraction

### Directory Structure

```
app/
├── Services/VideoExtraction/
│   ├── Contracts/
│   │   ├── DriverInterface.php
│   │   ├── ExtractorInterface.php
│   │   └── MetadataInterface.php
│   ├── Drivers/
│   │   ├── AbstractDriver.php
│   │   ├── YouTubeDriver.php
│   │   ├── TikTokDriver.php
│   │   ├── InstagramDriver.php
│   │   └── FacebookDriver.php
│   ├── Factory/
│   │   └── DriverFactory.php
│   ├── Registry/
│   │   └── DriverRegistry.php
│   ├── DTOs/
│   │   └── ExtractionResult.php
│   └── Exceptions/
├── Events/
│   ├── VideoExtractionRequested.php
│   ├── ExtractionCompleted.php
│   └── ExtractionFailed.php
├── Jobs/
│   ├── ProcessVideoExtraction.php
│   ├── RefreshVideoMetadata.php
│   ├── ValidateExtractedContent.php
│   └── CleanupExtractionData.php
├── Listeners/
│   ├── HandleExtractionRequest.php
│   └── UpdateExtractionStatistics.php
└── Http/Controllers/Api/
    └── VideoExtractionController.php
```

## Prerequisites

### yt-dlp Installation

The system requires yt-dlp to be installed on your system:

```bash
# Install yt-dlp
pip install yt-dlp

# Or using package manager (Ubuntu/Debian)
sudo apt install yt-dlp

# Or using Homebrew (macOS)
brew install yt-dlp
```

### Configuration

1. **Environment Variables**:
```env
# yt-dlp configuration
YT_DLP_BINARY_PATH=/usr/local/bin/yt-dlp
YT_DLP_TIMEOUT=300
YT_DLP_MAX_RETRIES=3

# Platform drivers
YOUTUBE_DRIVER_ENABLED=true
TIKTOK_DRIVER_ENABLED=true
INSTAGRAM_DRIVER_ENABLED=true
FACEBOOK_DRIVER_ENABLED=true

# Queue configuration
QUEUE_CONNECTION=database
```

2. **Publish Configuration**:
```bash
php artisan vendor:publish --tag=video-extraction-config
```

## Usage

### API Endpoints

#### 1. Extract Video
```http
POST /api/v1/extract
Content-Type: application/json

{
    "url": "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
    "quality": "720p",
    "format": "mp4"
}
```

Response:
```json
{
    "success": true,
    "message": "Extraction request submitted successfully",
    "data": {
        "session_id": "uuid-here",
        "status": "pending",
        "platform": "youtube",
        "quality": "720p",
        "format": "mp4",
        "estimated_processing_time": 30
    }
}
```

#### 2. Check Status
```http
GET /api/v1/extract/status/{sessionId}
```

Response (completed):
```json
{
    "success": true,
    "data": {
        "session_id": "uuid-here",
        "status": "completed",
        "platform": "youtube",
        "quality": "720p",
        "format": "mp4",
        "result": {
            "title": "Video Title",
            "thumbnail_url": "https://...",
            "duration": 180,
            "file_size": 15728640,
            "download_url": "https://...",
            "expires_at": "2024-01-01T12:00:00Z"
        }
    }
}
```

#### 3. Get Supported Platforms
```http
GET /api/v1/extract/platforms
```

### Console Commands

#### Test Video Extraction
```bash
php artisan video-extraction:test "https://www.youtube.com/watch?v=dQw4w9WgXcQ" --quality=720p --format=mp4
```

### Programmatic Usage

```php
use App\Services\VideoExtraction\Factory\DriverFactory;
use App\Enums\VideoQuality;
use App\Enums\VideoFormat;

// Inject or resolve the factory
$factory = app(DriverFactory::class);

// Create driver for URL
$driver = $factory->create('https://www.youtube.com/watch?v=dQw4w9WgXcQ');

// Extract metadata
$result = $driver->extractMetadata($url, [
    'quality' => VideoQuality::Q720P,
    'format' => VideoFormat::MP4,
]);

// Access extracted data
echo $result->getTitle();
echo $result->getDownloadUrl();
```

## Supported Platforms

| Platform | Qualities | Formats | Notes |
|----------|-----------|---------|-------|
| YouTube | 144p, 360p, 720p, 1080p | mp4, webm, mp3 | Full support |
| TikTok | 360p, 720p | mp4, mp3 | Short videos |
| Instagram | 360p, 720p, 1080p | mp4, mp3 | Posts, Reels, IGTV |
| Facebook | 360p, 720p, 1080p | mp4, mp3 | Public videos |

## Queue System

The system uses Laravel queues for asynchronous processing:

### Job Types

1. **ProcessVideoExtraction**: Main extraction job
2. **RefreshVideoMetadata**: Updates expired metadata
3. **ValidateExtractedContent**: Validates download URLs
4. **CleanupExtractionData**: Removes old/expired data

### Queue Configuration

```bash
# Run queue worker
php artisan queue:work

# Run specific queue
php artisan queue:work --queue=premium,pro,basic,default

# Schedule cleanup job (add to scheduler)
php artisan schedule:work
```

## Events and Listeners

### Events

- `VideoExtractionRequested`: Fired when extraction is requested
- `ExtractionCompleted`: Fired when extraction succeeds
- `ExtractionFailed`: Fired when extraction fails

### Listeners

- `HandleExtractionRequest`: Dispatches extraction jobs
- `UpdateExtractionStatistics`: Updates usage statistics

## Error Handling

The system includes comprehensive error handling:

### Exception Types

- `UnsupportedPlatformException`: Platform not supported
- `InvalidUrlException`: Malformed or invalid URL
- `ExtractionFailedException`: General extraction failure
- `RateLimitExceededException`: Rate limit exceeded

### Retry Logic

- Jobs automatically retry on failure (configurable)
- Exponential backoff for rate limiting
- Different retry strategies per platform

## Monitoring and Logging

### Logging

All extraction activities are logged with appropriate levels:

```php
Log::info('Video extraction completed', [
    'session_id' => $sessionId,
    'platform' => $platform,
    'processing_time' => $processingTime,
]);
```

### Statistics

The system tracks:
- Extraction success/failure rates per platform
- Processing times and performance metrics
- API usage statistics
- Error categories and frequencies

## Security Considerations

1. **URL Validation**: All URLs are validated before processing
2. **Rate Limiting**: Per-platform rate limiting to prevent abuse
3. **Input Sanitization**: All inputs are sanitized and validated
4. **Temporary Files**: Automatic cleanup of temporary files
5. **Access Control**: API key-based access control

## Performance Optimization

1. **Caching**: Metadata caching to reduce redundant extractions
2. **Queue Prioritization**: Different queues for different user tiers
3. **Resource Limits**: Memory and execution time limits
4. **Concurrent Processing**: Configurable concurrent extraction limits

## Troubleshooting

### Common Issues

1. **yt-dlp not found**: Ensure yt-dlp is installed and path is correct
2. **Permission errors**: Check file permissions for temp directory
3. **Queue not processing**: Ensure queue worker is running
4. **Rate limiting**: Implement delays between requests

### Debug Commands

```bash
# Test driver functionality
php artisan video-extraction:test "URL"

# Check queue status
php artisan queue:monitor

# Clear failed jobs
php artisan queue:flush
```

## Extending the System

### Adding New Platforms

1. Create new driver extending `AbstractDriver`
2. Implement required methods
3. Register driver in `DriverFactory`
4. Add platform to enum
5. Update configuration

### Custom Extraction Logic

Drivers can be customized by overriding methods in the abstract driver or implementing custom extraction logic using yt-dlp options.

## License

This video extraction system is part of the social-downloader project and follows the same license terms.
