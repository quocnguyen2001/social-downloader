<?php

return [
    'api_key_status' => [
        'active' => 'Hoạt động',
        'inactive' => 'Không hoạt động',
        'suspended' => 'Tạm ngưng',
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
        'pending' => 'Đang chờ',
        'fetching_metadata' => 'Đang lấy thông tin',
        'metadata_fetched' => 'Đã lấy thông tin',
        'ready_for_download' => 'Sẵn sàng tải xuống',
    ],

    'download_option_status' => [
        'downloaded' => 'Đã tải xuống',
        'cdn' => 'CDN',
        'processing' => 'Đang xử lý',
        'failed' => 'Thất bại',
    ],

    'payment_method' => [
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        'credit_card' => 'Thẻ tín dụng',
        'paypal' => 'PayPal',
        'crypto' => 'Tiền điện tử',
    ],

    'http_method' => [
        'GET' => 'GET',
        'POST' => 'POST',
        'PUT' => 'PUT',
        'DELETE' => 'DELETE',
    ],

    'order_status' => [
        'pending' => 'Đang chờ',
        'processing' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
    ],
];
