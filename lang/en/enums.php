<?php

return [
    'api_key_status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'suspended' => 'Suspended',
    ],

    'platform' => [
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
    ],

    'video_quality' => [
        '144p' => '144p',
        '360p' => '360p',
        '720p' => '720p',
        '1080p' => '1080p',
    ],

    'video_format' => [
        'mp4' => 'MP4',
        'mp3' => 'MP3',
        'webm' => 'WebM',
    ],

    'download_session_status' => [
        'pending' => 'Pending',
        'fetching_metadata' => 'Fetching Metadata',
        'metadata_fetched' => 'Metadata Fetched',
        'ready_for_download' => 'Ready for Download',
    ],

    'download_option_status' => [
        'downloaded' => 'Downloaded',
        'cdn' => 'CDN',
        'processing' => 'Processing',
        'failed' => 'Failed',
    ],

    'payment_method' => [
        'bank_transfer' => 'Bank Transfer',
        'credit_card' => 'Credit Card',
        'paypal' => 'PayPal',
        'crypto' => 'Cryptocurrency',
    ],

    'http_method' => [
        'GET' => 'GET',
        'POST' => 'POST',
        'PUT' => 'PUT',
        'DELETE' => 'DELETE',
    ],

    'order_status' => [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
    ],
];
