<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Filament Badge Colors Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains centralized color definitions for Filament badges
    | and other UI elements to ensure consistency across the application.
    |
    */

    'status' => [
        'api_key' => [
            'active' => 'success',
            'inactive' => 'warning',
            'suspended' => 'danger',
        ],
        'download_session' => [
            'pending' => 'warning',
            'fetching_metadata' => 'info',
            'metadata_fetched' => 'primary',
            'ready_for_download' => 'success',
        ],
        'transaction' => [
            'completed' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
        ],
        'order' => [
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
        ],
    ],

    'platform' => [
        'youtube' => 'danger',
        'tiktok' => 'warning',
        'instagram' => 'success',
        'facebook' => 'primary',
    ],

    'platform_icons' => [
        'youtube' => 'heroicon-o-play',
        'tiktok' => 'heroicon-o-musical-note',
        'instagram' => 'heroicon-o-camera',
        'facebook' => 'heroicon-o-users',
        'default' => 'heroicon-o-globe-alt',
    ],

    'http_status' => [
        200 => 'success',
        400 => 'warning',
        404 => 'warning',
        500 => 'danger',
    ],

    'membership_plan' => [
        'billing_cycle' => [
            'monthly' => 'info',
            'yearly' => 'success',
            'lifetime' => 'warning',
        ],
        'plan_type' => [
            'free' => 'gray',
            'basic' => 'info',
            'pro' => 'success',
            'premium' => 'warning',
            'enterprise' => 'danger',
        ],
    ],

    'usage_levels' => [
        'low' => 'success',      // 0-60%
        'medium' => 'warning',   // 60-80%
        'high' => 'danger',      // 80%+
    ],

    'token_count' => [
        'none' => 'gray',        // 0 tokens
        'low' => 'success',      // 1-2 tokens
        'medium' => 'warning',   // 3-5 tokens
        'high' => 'danger',      // 5+ tokens
    ],
];
