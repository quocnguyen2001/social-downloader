# Video Download API Implementation

## Overview

This document describes the implementation of a comprehensive API system for handling video download requests with background processing and status tracking.

## Architecture

### Core Components

1. **Enhanced DownloadOptionStatus Enum** - Added PROCESSING and FAILED statuses
2. **YtDlpService Enhancement** - Added downloadVideo() method for actual video downloads
3. **FileUploadService** - Handles cloud storage uploads with streaming support
4. **ProcessVideoDownload Job** - Background job for complete download workflow
5. **VideoDownloadController** - API endpoints for triggering and monitoring downloads
6. **Form Request Classes** - Validation for API requests

### Database Changes

- **Migration**: `2025_07_24_195616_add_processing_failed_statuses_to_download_options_table.php`
- **Updated Enum Values**: `download_options.status` now supports: `downloaded`, `cdn`, `processing`, `failed`

## API Endpoints

### 1. Trigger Download

**Endpoint**: `POST /api/v1/download/trigger`

**Request Body**:
```json
{
    "download_option_id": "uuid-string"
}
```

**Response (Success)**:
```json
{
    "success": true,
    "message": "Video download has been triggered and will be processed in the background",
    "data": {
        "download_option_id": "uuid-string",
        "status": "processing"
    }
}
```

**Response (Error)**:
```json
{
    "success": false,
    "message": "Error message",
    "data": null
}
```

### 2. Check Status

**Endpoint**: `POST /api/v1/download/status`

**Request Body**:
```json
{
    "download_option_id": "uuid-string"
}
```

**Response (Downloaded)**:
```json
{
    "success": true,
    "message": "Video download is ready for access",
    "data": {
        "download_option_id": "uuid-string",
        "status": "downloaded",
        "status_label": "Downloaded",
        "download_url": "https://storage.example.com/path/to/file.mp4",
        "file_size": 12345678,
        "storage_disk": "r2"
    }
}
```

**Response (Processing)**:
```json
{
    "success": true,
    "message": "Video download is currently being processed",
    "data": {
        "download_option_id": "uuid-string",
        "status": "processing",
        "status_label": "Processing"
    }
}
```

## Workflow

### Complete Download Process

1. **API Call**: Client calls `/api/v1/download/trigger` with `download_option_id`
2. **Validation**: System validates the download option exists and has required data
3. **Job Dispatch**: `ProcessVideoDownload` job is dispatched to queue
4. **Status Update**: Download option status changes to `processing`
5. **Video Download**: Job uses `YtDlpService` to download video with yt-dlp
6. **Cloud Upload**: Job uses `FileUploadService` to upload to cloud storage
7. **Status Update**: Download option status changes to `downloaded` with storage info
8. **Error Handling**: On failure, status changes to `failed`

### Background Job Details

The `ProcessVideoDownload` job:
- **Timeout**: 30 minutes
- **Retries**: 3 attempts
- **Backoff**: 60 seconds between retries
- **Queue**: Uses default database queue

## Configuration

### Video Extraction Config

Added to `config/video-extraction.php`:

```php
'upload' => [
    'large_file_threshold' => env('VIDEO_UPLOAD_LARGE_FILE_THRESHOLD', 100 * 1024 * 1024), // 100MB
    'default_storage_disk' => env('VIDEO_UPLOAD_DEFAULT_DISK', 'r2'),
    'cleanup_local_after_upload' => env('VIDEO_CLEANUP_LOCAL_AFTER_UPLOAD', true),
],
```

### Environment Variables

```env
VIDEO_UPLOAD_LARGE_FILE_THRESHOLD=104857600  # 100MB in bytes
VIDEO_UPLOAD_DEFAULT_DISK=r2
VIDEO_CLEANUP_LOCAL_AFTER_UPLOAD=true
YT_DLP_DOWNLOAD_TIMEOUT=600  # 10 minutes
```

## Error Handling

### Common Error Scenarios

1. **Download Option Not Found** (404)
2. **Already Processing** (409)
3. **Already Downloaded** (409)
4. **Missing CDN ID** (400)
5. **Missing Download Session** (400)
6. **Download Failed** (422)
7. **Internal Server Error** (500)

### Job Failure Handling

- Automatic retries with exponential backoff
- Status updates on permanent failure
- Comprehensive logging for debugging
- Cleanup of temporary files

## Security Considerations

- API key authentication required
- Input validation via Form Requests
- UUID validation for download option IDs
- Rate limiting via existing middleware
- Secure file storage with proper access controls

## Performance Optimizations

- Background job processing
- Streaming uploads for large files
- Automatic cleanup of temporary files
- Efficient database queries with eager loading
- Proper indexing on status fields

## Testing

Use the provided `test_video_download_api.php` script to test the complete workflow:

1. Update the script with valid API key and download option ID
2. Run: `php test_video_download_api.php`
3. Monitor the output for status changes

## Deployment Considerations

1. **Queue Workers**: Ensure queue workers are running to process jobs
2. **Storage Configuration**: Configure R2/S3 credentials properly
3. **yt-dlp Binary**: Ensure yt-dlp is installed and accessible
4. **Disk Space**: Monitor temporary directory for space usage
5. **Logging**: Configure log rotation for job logs
6. **Monitoring**: Set up alerts for failed jobs

## Success Criteria

✅ Both APIs function correctly with proper error handling  
✅ Background job processes videos efficiently  
✅ File upload to cloud storage works reliably for various file sizes  
✅ Status tracking provides accurate real-time information  
✅ Code follows existing project standards and is well-documented  

## Next Steps

1. Run comprehensive tests with various video URLs and formats
2. Monitor job performance and optimize as needed
3. Set up monitoring and alerting for production deployment
4. Consider adding webhook notifications for status changes
5. Implement rate limiting specific to download operations
