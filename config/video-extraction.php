<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Video Extraction Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the video extraction system.
    | You can configure drivers, yt-dlp settings, and other extraction options.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default video extraction driver that will be used
    | when no specific driver is requested. This should be one of the supported
    | platforms: youtube, tiktok, instagram, facebook.
    |
    */

    'default_driver' => env('VIDEO_EXTRACTION_DEFAULT_DRIVER', 'youtube'),

    /*
    |--------------------------------------------------------------------------
    | yt-dlp Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for yt-dlp, the underlying tool used for video
    | extraction. Make sure yt-dlp is installed on your system.
    |
    */

    'yt_dlp' => [
        'binary_path' => env('YT_DLP_BINARY_PATH', '/usr/local/bin/yt-dlp'),
        'timeout' => env('YT_DLP_TIMEOUT', 300), // 5 minutes for metadata extraction
        'download_timeout' => env('YT_DLP_DOWNLOAD_TIMEOUT', 600), // 10 minutes for video download
        'max_retries' => env('YT_DLP_MAX_RETRIES', 3),
        'user_agent' => env('YT_DLP_USER_AGENT', 'Mozilla/5.0 (compatible; VideoDownloader/1.0)'),
        'output_template' => env('YT_DLP_OUTPUT_TEMPLATE', '%(title)s.%(ext)s'),
        'extract_flat' => env('YT_DLP_EXTRACT_FLAT', false),
        'no_warnings' => env('YT_DLP_NO_WARNINGS', true),
        'ignore_errors' => env('YT_DLP_IGNORE_ERRORS', false),
        'using_cookies' => env('YT_DLP_USING_COOKIES', false),
        'cookies_file_path' => env('YT_DLP_COOKIES_FILE_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | FFmpeg Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for FFmpeg video conversion to ensure MP4 output.
    |
    */

    'ffmpeg' => [
        'binary_path' => env('FFMPEG_BINARY_PATH', 'ffmpeg'),
        'enabled' => env('FFMPEG_CONVERSION_ENABLED', true),
        'quality' => [
            'crf' => env('FFMPEG_CRF', 23), // Constant Rate Factor (23 for better Apple compatibility)
            'preset' => env('FFMPEG_PRESET', 'medium'), // Encoding speed preset (medium for better compatibility)
            'audio_bitrate' => env('FFMPEG_AUDIO_BITRATE', '128k'),
            'audio_sample_rate' => env('FFMPEG_AUDIO_SAMPLE_RATE', '44100'), // Standard audio sample rate
        ],
        'apple_compatibility' => [
            'enabled' => env('FFMPEG_APPLE_COMPATIBILITY', true),
            'video_profile' => env('FFMPEG_VIDEO_PROFILE', 'high'), // H.264 profile for Apple devices
            'video_level' => env('FFMPEG_VIDEO_LEVEL', '4.0'), // H.264 level constraint
            'pixel_format' => env('FFMPEG_PIXEL_FORMAT', 'yuv420p'), // Universal pixel format
            'max_width' => env('FFMPEG_MAX_WIDTH', 1920), // Maximum width for compatibility
            'max_height' => env('FFMPEG_MAX_HEIGHT', 1080), // Maximum height for compatibility
            'max_bitrate' => env('FFMPEG_MAX_BITRATE', '5000k'), // Maximum video bitrate
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Download Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for local video downloads before uploading to R2 storage.
    | This is part of the CORS workaround solution.
    |
    */

    'local_download' => [
        'temp_path' => env('VIDEO_TEMP_DOWNLOAD_PATH', 'temp-downloads'),
        'cleanup_on_success' => env('VIDEO_CLEANUP_ON_SUCCESS', true),
        'cleanup_on_error' => env('VIDEO_CLEANUP_ON_ERROR', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for uploading downloaded videos to cloud storage.
    |
    */

    'upload' => [
        'large_file_threshold' => env('VIDEO_UPLOAD_LARGE_FILE_THRESHOLD', 100 * 1024 * 1024), // 100MB
        'default_storage_disk' => env('VIDEO_UPLOAD_DEFAULT_DISK', 'r2'),
        'cleanup_local_after_upload' => env('VIDEO_CLEANUP_LOCAL_AFTER_UPLOAD', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for each platform driver. Each driver can have
    | its own specific settings and yt-dlp options.
    |
    */

    'drivers' => [
        'youtube' => [
            'enabled' => env('YOUTUBE_DRIVER_ENABLED', true),
            'priority' => 100,
            'supported_qualities' => ['144p', '360p', '720p', '1080p'],
            'supported_formats' => ['mp4', 'webm', 'mp3'],
            'yt_dlp_options' => [
                '--extract-flat' => false,
                '--write-info-json' => true,
                '--write-thumbnail' => false,
            ],
        ],

        'tiktok' => [
            'enabled' => env('TIKTOK_DRIVER_ENABLED', true),
            'priority' => 90,
            'supported_qualities' => ['360p', '720p'],
            'supported_formats' => ['mp4', 'mp3'],
            'yt_dlp_options' => [
                '--format' => 'best',
                '--extract-flat' => false,
                '--write-info-json' => true,
            ],
        ],

        'instagram' => [
            'enabled' => env('INSTAGRAM_DRIVER_ENABLED', true),
            'priority' => 80,
            'supported_qualities' => ['144p', '360p', '720p', '1080p'],
            'supported_formats' => ['mp4', 'mp3'],
            'yt_dlp_options' => [
                '--extract-flat' => false,
                '--write-info-json' => true,
                '--write-thumbnail' => false,
                '--no-check-certificate' => true,
                '--user-agent' => 'Mozilla/5.0 (compatible; VideoDownloader/1.0)',
            ],
        ],

        'facebook' => [
            'enabled' => env('FACEBOOK_DRIVER_ENABLED', true),
            'priority' => 70,
            'supported_qualities' => ['360p', '720p', '1080p'],
            'supported_formats' => ['mp4', 'mp3'],
            'yt_dlp_options' => [
                '--format' => 'best',
                '--extract-flat' => false,
                '--write-info-json' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Quality Settings
    |--------------------------------------------------------------------------
    |
    | Default quality settings and mappings for video extraction.
    |
    */

    'quality' => [
        'default' => env('VIDEO_EXTRACTION_DEFAULT_QUALITY', '720p'),
        'fallback' => env('VIDEO_EXTRACTION_FALLBACK_QUALITY', '360p'),
        // Mappings removed - use specific format IDs instead of generic selectors
    ],

    /*
    |--------------------------------------------------------------------------
    | Format Settings
    |--------------------------------------------------------------------------
    |
    | Default format settings and mappings for video extraction.
    |
    */

    'format' => [
        'default' => env('VIDEO_EXTRACTION_DEFAULT_FORMAT', 'mp4'),
        // Mappings removed - use specific format IDs instead of generic selectors
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for caching extraction results and metadata.
    |
    */

    'cache' => [
        'enabled' => env('VIDEO_EXTRACTION_CACHE_ENABLED', true),
        'ttl' => env('VIDEO_EXTRACTION_CACHE_TTL', 3600), // 1 hour
        'prefix' => env('VIDEO_EXTRACTION_CACHE_PREFIX', 'video_extraction'),
        'store' => env('VIDEO_EXTRACTION_CACHE_STORE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Temporary Files
    |--------------------------------------------------------------------------
    |
    | Configuration for temporary file handling during extraction.
    |
    */

    'temp' => [
        'directory' => env('VIDEO_EXTRACTION_TEMP_DIR', storage_path('app/temp/video-extraction')),
        'cleanup_after' => env('VIDEO_EXTRACTION_TEMP_CLEANUP_HOURS', 24), // hours
        'max_size' => env('VIDEO_EXTRACTION_TEMP_MAX_SIZE', 1024 * 1024 * 1024), // 1GB
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting extraction requests per platform.
    |
    */

    'rate_limiting' => [
        'enabled' => env('VIDEO_EXTRACTION_RATE_LIMITING_ENABLED', true),
        'per_platform' => [
            'youtube' => [
                'requests_per_minute' => env('YOUTUBE_RATE_LIMIT_PER_MINUTE', 60),
                'requests_per_hour' => env('YOUTUBE_RATE_LIMIT_PER_HOUR', 1000),
            ],
            'tiktok' => [
                'requests_per_minute' => env('TIKTOK_RATE_LIMIT_PER_MINUTE', 30),
                'requests_per_hour' => env('TIKTOK_RATE_LIMIT_PER_HOUR', 500),
            ],
            'instagram' => [
                'requests_per_minute' => env('INSTAGRAM_RATE_LIMIT_PER_MINUTE', 20),
                'requests_per_hour' => env('INSTAGRAM_RATE_LIMIT_PER_HOUR', 300),
            ],
            'facebook' => [
                'requests_per_minute' => env('FACEBOOK_RATE_LIMIT_PER_MINUTE', 20),
                'requests_per_hour' => env('FACEBOOK_RATE_LIMIT_PER_HOUR', 300),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging extraction activities.
    |
    */

    'logging' => [
        'enabled' => env('VIDEO_EXTRACTION_LOGGING_ENABLED', true),
        'level' => env('VIDEO_EXTRACTION_LOG_LEVEL', 'info'),
        'channel' => env('VIDEO_EXTRACTION_LOG_CHANNEL', 'default'),
        'log_yt_dlp_output' => env('VIDEO_EXTRACTION_LOG_YT_DLP_OUTPUT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    |
    | Security-related configuration options.
    |
    */

    'security' => [
        'allowed_domains' => env('VIDEO_EXTRACTION_ALLOWED_DOMAINS', null), // comma-separated list
        'blocked_domains' => env('VIDEO_EXTRACTION_BLOCKED_DOMAINS', null), // comma-separated list
        'max_url_length' => env('VIDEO_EXTRACTION_MAX_URL_LENGTH', 2048),
        'validate_ssl' => env('VIDEO_EXTRACTION_VALIDATE_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance
    |--------------------------------------------------------------------------
    |
    | Performance-related configuration options.
    |
    */

    'performance' => [
        'concurrent_extractions' => env('VIDEO_EXTRACTION_CONCURRENT_EXTRACTIONS', 5),
        'memory_limit' => env('VIDEO_EXTRACTION_MEMORY_LIMIT', '512M'),
        'max_execution_time' => env('VIDEO_EXTRACTION_MAX_EXECUTION_TIME', 300), // 5 minutes

        // Legacy download speed configuration (maintained for backward compatibility)
        'download_speed_mbps' => env('VIDEO_EXTRACTION_DOWNLOAD_SPEED_MBPS', 1), // 1 MB/s for download time estimation

        // Enhanced granular speed control configuration
        'download_speed_control' => [
            'enabled' => env('VIDEO_EXTRACTION_GRANULAR_SPEED_ENABLED', false),
            'data_amount_mb' => env('VIDEO_EXTRACTION_SPEED_DATA_AMOUNT_MB', 100), // Data amount in MB
            'time_duration_seconds' => env('VIDEO_EXTRACTION_SPEED_TIME_DURATION', 30), // Time duration in seconds
            'calculation_method' => env('VIDEO_EXTRACTION_SPEED_CALCULATION_METHOD', 'granular'), // 'granular' or 'legacy'
        ],
    ],

];
