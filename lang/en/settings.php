<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Settings Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used for various settings pages
    | throughout the application. You are free to modify these language
    | lines according to your application's requirements.
    |
    */

    'general' => [
        'title' => 'General Settings',
        'description' => 'Manage basic website information and configuration settings',
        'navigation_label' => 'General Settings',
        'sections' => [
            'site_information' => [
                'title' => 'Site Information',
                'description' => 'Basic website information and configuration',
            ],
            'contact_information' => [
                'title' => 'Contact Information',
                'description' => 'Administrative and support contact details',
            ],
            'maintenance_mode' => [
                'title' => 'Maintenance Mode',
                'description' => 'System maintenance settings',
            ],
            'seo_settings' => [
                'title' => 'SEO Settings',
                'description' => 'Search engine optimization settings',
            ],
            'copyright_information' => [
                'title' => 'Copyright Information',
                'description' => 'Website copyright details',
            ],
            'brand_assets' => [
                'title' => 'Brand Assets',
                'description' => 'Logo and favicon management',
            ],
            'legal_pages' => [
                'title' => 'Legal Pages',
                'description' => 'Links to legal documents',
            ],
        ],
        'fields' => [
            'site_name' => [
                'label' => 'Site Name',
                'placeholder' => 'Social Downloader',
            ],
            'site_description' => [
                'label' => 'Site Description',
                'placeholder' => 'Download videos and media from social platforms',
            ],
            'site_url' => [
                'label' => 'Site URL',
                'placeholder' => 'https://example.com',
            ],
            'default_timezone' => [
                'label' => 'Default Timezone',
            ],
            'admin_email' => [
                'label' => 'Admin Email',
                'placeholder' => 'admin@example.com',
            ],
            'support_email' => [
                'label' => 'Support Email',
                'placeholder' => 'support@example.com',
            ],
            'maintenance_mode' => [
                'label' => 'Enable Maintenance Mode',
                'helper_text' => 'Put the site in maintenance mode',
            ],
            'maintenance_message' => [
                'label' => 'Maintenance Message',
                'placeholder' => 'We are currently performing maintenance. Please check back later.',
            ],
            'meta_title' => [
                'label' => 'Meta Title',
                'placeholder' => 'Social Downloader - Download Videos from Social Platforms',
                'helper_text' => 'Recommended length: 50-60 characters',
            ],
            'meta_description' => [
                'label' => 'Meta Description',
                'placeholder' => 'Download videos and media from popular social platforms...',
                'helper_text' => 'Recommended length: 150-160 characters',
            ],
            'meta_keywords' => [
                'label' => 'Meta Keywords',
                'placeholder' => 'video downloader, social media downloader, youtube downloader',
                'helper_text' => 'Comma-separated keywords',
            ],
            'copyright_text' => [
                'label' => 'Copyright Text',
                'placeholder' => 'All rights reserved.',
            ],
            'copyright_year' => [
                'label' => 'Copyright Year',
                'helper_text' => 'Current year will be used if empty',
            ],
            'logo_path' => [
                'label' => 'Site Logo',
                'helper_text' => 'Upload your site logo (JPEG, PNG, SVG - Max 2MB)',
            ],
            'favicon_path' => [
                'label' => 'Favicon',
                'helper_text' => 'Upload favicon (ICO, PNG - Max 512KB)',
            ],
            'terms_of_service_url' => [
                'label' => 'Terms of Service URL',
                'placeholder' => 'https://example.com/terms',
            ],
            'privacy_policy_url' => [
                'label' => 'Privacy Policy URL',
                'placeholder' => 'https://example.com/privacy',
            ],
        ],
    ],

    'cookie' => [
        'title' => 'Cookie Settings',
        'description' => 'Manage cookie configuration files for authenticated video extraction',
        'navigation_label' => 'Cookie Settings',
        'sections' => [
            'file_configuration' => [
                'title' => 'Cookie File Configuration',
                'description' => 'Upload cookie file for authenticated video extraction from platforms requiring login',
            ],
        ],
        'fields' => [
            'cookie_file_path' => [
                'label' => 'Cookie File',
                'helper_text' => 'Upload a .txt file containing cookies for authenticated video extraction. This enables downloading from platforms that require login credentials.',
                'placeholder' => 'No cookie file uploaded',
            ],
        ],
    ],
];
