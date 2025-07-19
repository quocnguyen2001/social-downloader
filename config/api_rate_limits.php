<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the rate limiting configuration for various API
    | endpoints. Each endpoint can have different limits based on the
    | sensitivity and expected usage patterns.
    |
    */

    'authentication' => [
        'registration' => [
            'max_attempts' => env('RATE_LIMIT_REGISTRATION_MAX', 3),
            'decay_minutes' => env('RATE_LIMIT_REGISTRATION_DECAY', 60),
            'key_prefix' => 'registration_attempts',
        ],
        'login' => [
            'max_attempts' => env('RATE_LIMIT_LOGIN_MAX', 5),
            'decay_minutes' => env('RATE_LIMIT_LOGIN_DECAY', 15),
            'key_prefix' => 'login_attempts',
        ],
        'password_reset' => [
            'max_attempts' => env('RATE_LIMIT_PASSWORD_RESET_MAX', 3),
            'decay_minutes' => env('RATE_LIMIT_PASSWORD_RESET_DECAY', 60),
            'key_prefix' => 'password_reset',
        ],
        'logout' => [
            'max_attempts' => env('RATE_LIMIT_LOGOUT_MAX', 10),
            'decay_minutes' => env('RATE_LIMIT_LOGOUT_DECAY', 5),
            'key_prefix' => 'logout_attempts',
        ],
    ],

    'api_endpoints' => [
        'video_extraction' => [
            'max_attempts' => env('RATE_LIMIT_EXTRACTION_MAX', 100),
            'decay_minutes' => env('RATE_LIMIT_EXTRACTION_DECAY', 60),
            'key_prefix' => 'video_extraction',
        ],
        'token_generation' => [
            'max_attempts' => env('RATE_LIMIT_TOKEN_GEN_MAX', 5),
            'decay_minutes' => env('RATE_LIMIT_TOKEN_GEN_DECAY', 60),
            'key_prefix' => 'token_generation',
        ],
    ],

    'global' => [
        'api_requests' => [
            'max_attempts' => env('RATE_LIMIT_GLOBAL_MAX', 1000),
            'decay_minutes' => env('RATE_LIMIT_GLOBAL_DECAY', 60),
            'key_prefix' => 'api_global',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Strategies
    |--------------------------------------------------------------------------
    |
    | Different strategies for rate limiting based on user type or
    | authentication status.
    |
    */

    'strategies' => [
        'guest' => [
            'multiplier' => 1.0,
            'description' => 'Standard rate limits for unauthenticated users',
        ],
        'authenticated' => [
            'multiplier' => 2.0,
            'description' => 'Higher rate limits for authenticated users',
        ],
        'premium' => [
            'multiplier' => 5.0,
            'description' => 'Premium rate limits for paid users',
        ],
        'admin' => [
            'multiplier' => 10.0,
            'description' => 'Administrative rate limits',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Messages
    |--------------------------------------------------------------------------
    |
    | Custom messages for different rate limiting scenarios.
    |
    */

    'messages' => [
        'too_many_requests' => 'Too many requests. Please try again in :seconds seconds.',
        'registration_limit' => 'Too many registration attempts. Please try again in :minutes minutes.',
        'login_limit' => 'Too many login attempts. Please try again in :minutes minutes.',
        'password_reset_limit' => 'Too many password reset requests. Please try again in :minutes minutes.',
        'api_limit' => 'API rate limit exceeded. Please try again in :minutes minutes.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for bypassing rate limits in certain scenarios.
    |
    */

    'bypass' => [
        'enabled' => env('RATE_LIMIT_BYPASS_ENABLED', false),
        'ips' => array_filter(explode(',', env('RATE_LIMIT_BYPASS_IPS', ''))),
        'user_ids' => array_filter(explode(',', env('RATE_LIMIT_BYPASS_USER_IDS', ''))),
    ],
];
