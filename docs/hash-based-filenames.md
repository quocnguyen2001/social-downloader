# Hash-Based Filename System

## Overview

The social-downloader system now uses a hash-based filename generation system to ensure consistent, predictable filenames for downloaded videos. This replaces the previous system that relied on video titles, which could be inconsistent and cause issues with file management.

## Benefits

### ✅ **Consistency**
- Same URL + format combination always generates the same hash
- Filenames remain stable regardless of video title changes
- Predictable naming for automated systems

### ✅ **Duplicate Detection**
- Content-based hashing enables efficient duplicate detection
- Same video with same format will have identical content hash
- Prevents unnecessary re-downloads

### ✅ **File System Safety**
- No special characters or problematic filename patterns
- Consistent length and format
- Works across all operating systems

### ✅ **Performance**
- Fast hash generation using SHA-256
- Efficient filename parsing and validation
- Minimal overhead during download process

## Filename Format

### Standard Format
```
{hash}_{timestamp}.{extension}
```

**Example:** `6b85076ecce04b95_20250725_191543.mp4`

### Components
- **Hash (16 chars)**: SHA-256 hash of URL + format + session (truncated to 16 characters)
- **Timestamp**: `YYYYMMDD_HHMMSS` format for uniqueness
- **Extension**: Determined by yt-dlp based on video format

### Without Timestamp (for consistency testing)
```
{hash}.{extension}
```

**Example:** `6b85076ecce04b95.mp4`

## Hash Generation Logic

### Input Components
1. **Normalized URL**: URL with non-content parameters removed
2. **CDN/Format ID**: The specific format being downloaded
3. **Session ID** (optional): For video+audio merging scenarios

### Hash Calculation
```php
$hashInput = normalizeUrl($url) . '|' . $cdnId;
if ($sessionId) {
    $hashInput .= '|' . $sessionId;
}
$hash = substr(hash('sha256', $hashInput), 0, 16);
```

### URL Normalization
- Removes tracking parameters
- Preserves content-relevant parameters (v, video_id, p, reel)
- Ensures consistent hashing for same content

## Usage Examples

### Testing Filename Generation
```bash
# Test with Facebook URL
php artisan filename:test "https://www.facebook.com/share/v/157e6ozkb2/" "best" --session_id="test-session"

# Test with Instagram URL
php artisan filename:test "https://www.instagram.com/p/DMKmjxuM4TX/" "best"

# Test without timestamp for consistency
php artisan filename:test "https://example.com/video" "720p" --no-timestamp
```

### Programmatic Usage
```php
use App\Services\VideoExtraction\FilenameService;

$filenameService = app(FilenameService::class);

// Generate stable filename
$filename = $filenameService->generateStableFilename(
    'https://www.instagram.com/p/DMKmjxuM4TX/',
    'best',
    'session-123'
);
// Result: "d1839ff7b9f8b2f4_20250725_191549.%(ext)s"

// Generate content hash for duplicate detection
$contentHash = $filenameService->generateContentHash(
    'https://www.instagram.com/p/DMKmjxuM4TX/',
    'best'
);
// Result: "d1839ff7b9f8b2f4"

// Check if filename is hash-based
$isHashBased = $filenameService->isHashBasedFilename('d1839ff7b9f8b2f4_20250725_191549.mp4');
// Result: true

// Extract hash from filename
$hash = $filenameService->extractHashFromFilename('d1839ff7b9f8b2f4_20250725_191549.mp4');
// Result: "d1839ff7b9f8b2f4"

// Generate display filename for UI
$displayName = $filenameService->generateDisplayFilename([
    'title' => 'Amazing Video',
    'uploader' => 'ChannelName'
], 'mp4');
// Result: "ChannelName - Amazing_Video.mp4"
```

## Integration Points

### YtDlpService
- Uses `FilenameService` to generate stable filenames
- Passes generated filename to yt-dlp via `-o` parameter
- Maintains backward compatibility with existing download flow

### FileUploadService
- Detects hash-based filenames automatically
- Skips adding additional unique IDs for hash-based files
- Preserves original hash-based naming in cloud storage

### Storage Structure
```
downloads/
├── 2025/01/25/
│   ├── 6b85076ecce04b95_20250125_143022.mp4
│   ├── d1839ff7b9f8b2f4_20250125_143055.mp4
│   └── legacy_uniqid_old_filename.mp4
└── 2025/01/26/
    └── ...
```

## Migration Strategy

### Backward Compatibility
- Existing files with old naming continue to work
- New downloads use hash-based naming
- FileUploadService handles both naming schemes

### Gradual Migration
1. **Phase 1**: New downloads use hash-based names
2. **Phase 2**: Optional migration command for existing files
3. **Phase 3**: Full transition to hash-based system

## Configuration

### Environment Variables
```env
# YT-DLP Configuration
YT_DLP_OUTPUT_TEMPLATE="%(title)s.%(ext)s"  # Overridden by hash-based system
YT_DLP_BINARY_PATH="/usr/local/bin/yt-dlp"
YT_DLP_DOWNLOAD_TIMEOUT=600

# Video Extraction
VIDEO_EXTRACTION_TEMP_DIRECTORY="/tmp/video-downloads"
VIDEO_EXTRACTION_CLEANUP_LOCAL=true
```

### Service Configuration
The `FilenameService` is automatically injected into:
- `YtDlpService`
- `FileUploadService`
- Any other services requiring filename generation

## Troubleshooting

### Common Issues

#### Hash Mismatch
**Problem**: Same URL generates different hashes
**Solution**: Check URL normalization - ensure consistent URL format

#### Filename Too Long
**Problem**: Generated filename exceeds filesystem limits
**Solution**: Hash is always 16 chars + timestamp (15 chars) = max 31 chars + extension

#### Legacy Filename Conflicts
**Problem**: Old and new naming schemes conflict
**Solution**: Use `isHashBasedFilename()` to detect and handle appropriately

### Debugging
```bash
# Test filename generation
php artisan filename:test "YOUR_URL" "FORMAT_ID"

# Check logs for filename generation
tail -f storage/logs/laravel.log | grep "Generated stable filename"
```

## Future Enhancements

### Planned Features
1. **Migration Command**: Rename existing files to hash-based format
2. **Duplicate Detection**: Use content hashes to identify duplicates
3. **Filename Analytics**: Track naming pattern usage
4. **Custom Hash Algorithms**: Support for different hashing methods

### Performance Optimizations
1. **Hash Caching**: Cache generated hashes for frequently accessed URLs
2. **Batch Processing**: Optimize hash generation for bulk operations
3. **Database Indexing**: Index content hashes for fast duplicate detection
