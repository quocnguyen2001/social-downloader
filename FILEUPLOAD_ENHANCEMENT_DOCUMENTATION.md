# FileUploadService Enhancement Documentation

## Overview
Enhanced the `FileUploadService` to add appropriate HTTP headers for media files uploaded to S3/R2 storage to improve download functionality. The enhancement adds Content-Type and Content-Disposition headers to facilitate easier and more reliable downloads for end users.

## Changes Made

### 1. Modified Upload Methods
- **`uploadSmallFile()`**: Added `$fileName` parameter and metadata handling for S3-compatible disks
- **`uploadLargeFile()`**: Added `$fileName` parameter and metadata handling for S3-compatible disks
- **`uploadFile()`**: Updated method calls to pass the filename parameter

### 2. New Private Methods Added

#### `getDiskName($disk): string`
- Determines the disk name from a disk instance
- Handles fallback logic for unknown disk configurations
- Returns 'local' as default fallback

#### `isS3CompatibleDisk(string $diskName): bool`
- Checks if a disk uses the S3 driver (covers both S3 and R2)
- Returns true for S3-compatible disks, false for local disks

#### `getMimeTypeFromExtension(string $fileName): string`
- Comprehensive MIME type mapping for media files
- Supports video, audio, image, and document formats
- Returns 'application/octet-stream' as fallback

#### `sanitizeFilenameForDisposition(string $fileName): string`
- Sanitizes filenames for Content-Disposition header
- Removes invalid characters and control characters
- Limits filename length while preserving extension
- Ensures valid filename output

#### `generateContentDisposition(string $fileName): string`
- Creates RFC 6266 compliant Content-Disposition header
- Format: `attachment; filename="sanitized_filename.ext"`

#### `getUploadMetadata(string $fileName): array`
- Combines Content-Type and Content-Disposition metadata
- Returns array suitable for S3 adapter

#### `uploadWithMetadata($disk, string $storageFilePath, string $fileContents, string $fileName): void`
- Uploads file content with metadata to S3-compatible disks
- Uses underlying Flysystem adapter for metadata support
- Includes graceful fallback to standard upload on failure

#### `uploadStreamWithMetadata($disk, string $storageFilePath, $stream, string $fileName): void`
- Uploads file stream with metadata to S3-compatible disks
- Uses underlying Flysystem adapter for streaming with metadata
- Includes graceful fallback to standard stream upload on failure

## Supported File Types and MIME Types

### Video Formats
- **mp4** → `video/mp4`
- **avi** → `video/x-msvideo`
- **mov** → `video/quicktime`
- **webm** → `video/webm`
- **mkv** → `video/x-matroska`
- **flv** → `video/x-flv`
- **wmv** → `video/x-ms-wmv`
- **m4v** → `video/x-m4v`
- **3gp** → `video/3gpp`
- **ogv** → `video/ogg`

### Audio Formats
- **mp3** → `audio/mpeg`
- **wav** → `audio/wav`
- **flac** → `audio/flac`
- **aac** → `audio/aac`
- **ogg** → `audio/ogg`
- **m4a** → `audio/x-m4a`
- **wma** → `audio/x-ms-wma`
- **opus** → `audio/opus`

### Image Formats
- **jpg/jpeg** → `image/jpeg`
- **png** → `image/png`
- **gif** → `image/gif`
- **webp** → `image/webp`
- **bmp** → `image/bmp`
- **svg** → `image/svg+xml`

### Document Formats
- **pdf** → `application/pdf`
- **txt** → `text/plain`
- **json** → `application/json`
- **xml** → `application/xml`

## Header Implementation Strategy

### Content-Type Header
- Automatically determined from file extension
- Uses comprehensive MIME type mapping
- Fallback to `application/octet-stream` for unknown types
- Ensures proper browser handling of different file types

### Content-Disposition Header
- Always set to `attachment` directive for forced downloads
- Includes sanitized original filename
- Handles special characters and encoding properly
- Follows RFC 6266 specifications
- Format: `Content-Disposition: attachment; filename="sanitized_name.ext"`

## Compatibility and Error Handling

### Storage Compatibility
- **S3 and R2**: Full metadata support with Content-Type and Content-Disposition headers
- **Local Storage**: Standard upload without headers (headers not applicable)
- **Other Drivers**: Graceful fallback to standard upload methods

### Error Handling
- Graceful fallback if metadata setting fails
- Detailed logging for debugging header issues
- Upload continues even if header setting fails
- Maintains existing upload success/failure logic
- No breaking changes to existing functionality

### Backward Compatibility
- All existing method signatures remain unchanged for public methods
- Private method changes are internal implementation details
- Existing code continues to work without modifications
- No impact on current upload workflows

## Testing Instructions

### 1. Test Different File Types
```php
// Test video file upload
$result = $fileUploadService->uploadFile('/path/to/video.mp4', 'test-video.mp4', 'r2');

// Test audio file upload
$result = $fileUploadService->uploadFile('/path/to/audio.mp3', 'test-audio.mp3', 's3');

// Test image file upload
$result = $fileUploadService->uploadFile('/path/to/image.jpg', 'test-image.jpg', 'r2');
```

### 2. Test Different Storage Disks
```php
// Test with R2 (should include headers)
$result = $fileUploadService->uploadFile($localPath, $fileName, 'r2');

// Test with S3 (should include headers)
$result = $fileUploadService->uploadFile($localPath, $fileName, 's3');

// Test with local (should work without headers)
$result = $fileUploadService->uploadFile($localPath, $fileName, 'local');
```

### 3. Test Large File Streaming
```php
// Test with file larger than threshold (100MB by default)
$result = $fileUploadService->uploadFile('/path/to/large-video.mp4', 'large-video.mp4', 'r2');
```

### 4. Test Filename Sanitization
```php
// Test with special characters in filename
$result = $fileUploadService->uploadFile($localPath, 'test<>file|name?.mp4', 'r2');
// Should sanitize to: test__file_name_.mp4
```

### 5. Verify Headers in S3/R2
After upload, check the object metadata in your S3/R2 console:
- **Content-Type**: Should match the file's MIME type
- **Content-Disposition**: Should be `attachment; filename="sanitized_name.ext"`

### 6. Test Download Functionality
```php
// Generate download URL and test in browser
$downloadUrl = Storage::disk('r2')->url($storageFilePath);
// Browser should prompt for download with correct filename
```

## Logging and Debugging

### Debug Logs
- File upload start and completion
- Metadata generation and application
- Fallback scenarios when metadata fails

### Warning Logs
- Metadata upload failures with fallback
- Filename sanitization issues
- Disk configuration problems

### Log Examples
```
[DEBUG] Uploading file with metadata: {"storage_file_path": "downloads/2025/01/27/video.mp4", "metadata": {"ContentType": "video/mp4", "ContentDisposition": "attachment; filename=\"video.mp4\""}}

[WARNING] Failed to upload with metadata, falling back to standard upload: {"storage_file_path": "downloads/2025/01/27/video.mp4", "error": "Adapter error message"}
```

## Performance Considerations

### No Performance Impact
- Header generation is lightweight
- MIME type lookup is O(1) hash table operation
- Filename sanitization uses efficient regex operations
- Fallback mechanism prevents upload failures

### Memory Usage
- No additional memory overhead for small files
- Streaming uploads maintain low memory footprint
- Metadata is minimal additional data

## Security Considerations

### Filename Sanitization
- Removes dangerous characters that could cause issues
- Prevents directory traversal attempts
- Limits filename length to prevent buffer overflows
- Removes control characters that could cause display issues

### Content-Type Validation
- MIME types are based on file extensions only
- Does not perform content inspection (by design)
- Relies on existing file validation in the application

## Future Enhancements

### Potential Improvements
1. **Content validation**: Add optional content-based MIME type detection
2. **Custom headers**: Allow passing additional custom headers
3. **Compression**: Add support for Content-Encoding headers
4. **Cache control**: Add configurable cache headers
5. **Metadata extraction**: Extract and store additional file metadata

### Configuration Options
Consider adding configuration options for:
- Custom MIME type mappings
- Header customization per disk
- Filename sanitization rules
- Fallback behavior preferences

## Conclusion

The FileUploadService enhancement successfully adds proper HTTP headers for media files uploaded to S3/R2 storage while maintaining full backward compatibility and robust error handling. The implementation follows Laravel best practices and provides comprehensive logging for debugging and monitoring.

The enhancement improves the user experience by ensuring:
- Correct Content-Type headers for proper browser handling
- Content-Disposition headers for reliable download functionality
- Sanitized filenames for security and compatibility
- Graceful fallbacks for maximum reliability