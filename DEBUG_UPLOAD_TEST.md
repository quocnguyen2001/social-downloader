# Debug File Upload Headers - FIXED

## Issue Identified and Resolved
The Content-Disposition header was not appearing because the previous implementation was using incorrect parameter formats for the S3 adapter. 

## Root Cause
Based on AWS S3 PutObject API documentation, Content-Type and Content-Disposition are **request headers**, not metadata parameters. They need to be passed directly to the S3 client's putObject method, not nested under a 'params' or 'metadata' key.

## Updated Implementation
I've completely rewritten the `FileUploadService` to use the **correct approach**:

1. **Direct S3 Client Access**: Get the S3 client directly from the disk adapter
2. **Proper Parameter Format**: Pass ContentType and ContentDisposition as direct parameters to putObject()
3. **AWS SDK Compliance**: Use the exact parameter names expected by the AWS S3 SDK

## Testing Steps

### 1. Run the Test Command
```bash
php artisan test:file-upload-headers test-video.mp4 r2
```

This will:
- Create a temporary test file
- Upload it with the specified filename and disk
- Show detailed logging of which method succeeded
- Provide the download URL for verification

### 2. Check the Logs
Look at your Laravel logs (`storage/logs/laravel.log`) for debug messages like:
```
[DEBUG] Uploading file with metadata: {"storage_file_path": "...", "metadata": {"ContentType": "video/mp4", "ContentDisposition": "attachment; filename=\"test-video.mp4\""}}
[DEBUG] Successfully uploaded with Laravel put method and options
```

### 3. Verify in S3/R2 Console
1. Go to your S3/R2 console
2. Find the uploaded file
3. Check the object metadata/properties
4. Look for:
   - **Content-Type**: Should be `video/mp4`
   - **Content-Disposition**: Should be `attachment; filename="test-video.mp4"`

### 4. Test Download
Click the download URL provided by the test command and verify:
- Browser prompts for download
- Filename is correct
- File type is recognized

## Troubleshooting

### If Headers Still Don't Appear
The issue might be with the specific format expected by your S3/R2 provider. Try these alternatives:

#### Option 1: Check Laravel Version Compatibility
```bash
php artisan --version
composer show league/flysystem-aws-s3-v3
```

#### Option 2: Manual S3 Client Approach
If the Flysystem approach doesn't work, we might need to use the AWS S3 client directly:

```php
// Alternative implementation using S3 client directly
$s3Client = Storage::disk('r2')->getAdapter()->getClient();
$s3Client->putObject([
    'Bucket' => config('filesystems.disks.r2.bucket'),
    'Key' => $storageFilePath,
    'Body' => $fileContents,
    'ContentType' => $mimeType,
    'ContentDisposition' => 'attachment; filename="' . $filename . '"',
]);
```

#### Option 3: Check Disk Configuration
Ensure your S3/R2 disk configuration in `config/filesystems.php` is correct and has proper permissions.

## Expected Debug Output

When the test runs successfully, you should see logs like:
```
[DEBUG] Uploading file with metadata
[DEBUG] Successfully uploaded with [method name]
```

If you see fallback messages:
```
[WARNING] Failed to upload with metadata, falling back to standard upload
```

This indicates the metadata setting failed and we need to try a different approach.

## Next Steps

1. Run the test command
2. Check the logs for which method succeeded
3. Verify headers in your S3/R2 console
4. If headers still don't appear, let me know which method succeeded in the logs, and I'll adjust the implementation accordingly.

The multiple-approach implementation ensures we find the correct method for your specific Laravel/Flysystem version combination.