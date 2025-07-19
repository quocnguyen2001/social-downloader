<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Security Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains security settings for API endpoints including
    | validation rules, sanitization options, and security headers.
    |
    */

    'validation' => [
        'max_request_size' => env('API_MAX_REQUEST_SIZE', 1024 * 1024), // 1MB
        'max_input_length' => env('API_MAX_INPUT_LENGTH', 10000),
        'max_array_depth' => env('API_MAX_ARRAY_DEPTH', 10),
        'required_headers' => [
            'Accept',
            'Content-Type', // For POST/PUT/PATCH requests
        ],
        'allowed_content_types' => [
            'application/json',
            'multipart/form-data',
            'application/x-www-form-urlencoded',
        ],
    ],

    'sanitization' => [
        'enabled' => env('API_SANITIZATION_ENABLED', true),
        'strip_html' => env('API_STRIP_HTML', true),
        'remove_null_bytes' => env('API_REMOVE_NULL_BYTES', true),
        'trim_whitespace' => env('API_TRIM_WHITESPACE', true),
        'remove_control_chars' => env('API_REMOVE_CONTROL_CHARS', true),
        'fields_to_skip' => [
            'password',
            'password_confirmation',
            'token',
        ],
    ],

    'security_headers' => [
        'enabled' => env('API_SECURITY_HEADERS_ENABLED', true),
        'headers' => [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'",
        ],
    ],

    'logging' => [
        'log_suspicious_requests' => env('API_LOG_SUSPICIOUS', true),
        'log_failed_validations' => env('API_LOG_FAILED_VALIDATIONS', true),
        'log_rate_limit_hits' => env('API_LOG_RATE_LIMITS', true),
        'suspicious_patterns' => [
            'sql_injection' => '/(\bunion\b|\bselect\b|\binsert\b|\bdelete\b|\bdrop\b|\bupdate\b).*(\bfrom\b|\binto\b|\bwhere\b)/i',
            'xss_attempt' => '/<script|javascript:|vbscript:|onload=|onerror=/i',
            'path_traversal' => '/\.\.[\/\\\\]/i',
            'command_injection' => '/(\||;|&|\$\(|\`)/i',
            'ldap_injection' => '/(\(|\)|&|\||!)/i',
            'xpath_injection' => '/(\[|\]|\'|\"|\|)/i',
        ],
    ],

    'ip_filtering' => [
        'enabled' => env('API_IP_FILTERING_ENABLED', false),
        'whitelist' => array_filter(explode(',', env('API_IP_WHITELIST', ''))),
        'blacklist' => array_filter(explode(',', env('API_IP_BLACKLIST', ''))),
        'block_tor' => env('API_BLOCK_TOR', false),
        'block_vpn' => env('API_BLOCK_VPN', false),
    ],

    'user_agent_filtering' => [
        'enabled' => env('API_USER_AGENT_FILTERING_ENABLED', false),
        'blocked_patterns' => [
            '/bot/i',
            '/crawler/i',
            '/spider/i',
            '/scraper/i',
        ],
        'allowed_patterns' => [
            '/curl/i',
            '/postman/i',
            '/insomnia/i',
        ],
    ],

    'honeypot' => [
        'enabled' => env('API_HONEYPOT_ENABLED', false),
        'field_name' => env('API_HONEYPOT_FIELD', 'website'),
        'log_attempts' => env('API_HONEYPOT_LOG', true),
    ],

    'csrf' => [
        'enabled' => env('API_CSRF_ENABLED', false),
        'exclude_routes' => [
            'api/auth/login',
            'api/auth/register',
            'api/auth/forgot-password',
            'api/auth/reset-password',
        ],
    ],

    'encryption' => [
        'encrypt_sensitive_logs' => env('API_ENCRYPT_LOGS', false),
        'sensitive_fields' => [
            'password',
            'token',
            'api_key',
            'secret',
            'private_key',
        ],
    ],

    'monitoring' => [
        'enabled' => env('API_MONITORING_ENABLED', true),
        'alert_thresholds' => [
            'failed_requests_per_minute' => env('API_ALERT_FAILED_REQUESTS', 100),
            'suspicious_requests_per_hour' => env('API_ALERT_SUSPICIOUS_REQUESTS', 50),
            'rate_limit_hits_per_hour' => env('API_ALERT_RATE_LIMITS', 200),
        ],
        'notification_channels' => [
            'log',
            // 'slack',
            // 'email',
        ],
    ],
];
