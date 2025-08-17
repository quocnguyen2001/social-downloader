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
        'title' => 'Cài Đặt Chung',
        'description' => 'Quản lý thông tin website cơ bản và cài đặt cấu hình',
        'navigation_label' => 'Cài Đặt Chung',
        'sections' => [
            'site_information' => [
                'title' => 'Thông Tin Website',
                'description' => 'Thông tin website cơ bản và cấu hình',
            ],
            'contact_information' => [
                'title' => 'Thông Tin Liên Hệ',
                'description' => 'Chi tiết liên hệ quản trị và hỗ trợ',
            ],
            'maintenance_mode' => [
                'title' => 'Chế Độ Bảo Trì',
                'description' => 'Cài đặt bảo trì hệ thống',
            ],
            'seo_settings' => [
                'title' => 'Cài Đặt SEO',
                'description' => 'Cài đặt tối ưu hóa công cụ tìm kiếm',
            ],
            'copyright_information' => [
                'title' => 'Thông Tin Bản Quyền',
                'description' => 'Chi tiết bản quyền website',
            ],
            'brand_assets' => [
                'title' => 'Tài Sản Thương Hiệu',
                'description' => 'Quản lý logo và favicon',
            ],
            'legal_pages' => [
                'title' => 'Trang Pháp Lý',
                'description' => 'Liên kết đến các tài liệu pháp lý',
            ],
        ],
        'fields' => [
            'site_name' => [
                'label' => 'Tên Website',
                'placeholder' => 'Social Downloader',
            ],
            'site_description' => [
                'label' => 'Mô Tả Website',
                'placeholder' => 'Tải video và media từ các nền tảng mạng xã hội',
            ],
            'site_url' => [
                'label' => 'URL Website',
                'placeholder' => 'https://example.com',
            ],
            'default_timezone' => [
                'label' => 'Múi Giờ Mặc Định',
            ],
            'admin_email' => [
                'label' => 'Email Quản Trị',
                'placeholder' => 'admin@example.com',
            ],
            'support_email' => [
                'label' => 'Email Hỗ Trợ',
                'placeholder' => 'support@example.com',
            ],
            'maintenance_mode' => [
                'label' => 'Bật Chế Độ Bảo Trì',
                'helper_text' => 'Đưa website vào chế độ bảo trì',
            ],
            'maintenance_message' => [
                'label' => 'Thông Báo Bảo Trì',
                'placeholder' => 'Chúng tôi đang thực hiện bảo trì. Vui lòng quay lại sau.',
            ],
            'meta_title' => [
                'label' => 'Meta Title',
                'placeholder' => 'Social Downloader - Tải Video Từ Các Nền Tảng Mạng Xã Hội',
                'helper_text' => 'Độ dài khuyến nghị: 50-60 ký tự',
            ],
            'meta_description' => [
                'label' => 'Meta Description',
                'placeholder' => 'Tải video và media từ các nền tảng mạng xã hội phổ biến...',
                'helper_text' => 'Độ dài khuyến nghị: 150-160 ký tự',
            ],
            'meta_keywords' => [
                'label' => 'Meta Keywords',
                'placeholder' => 'tải video, tải video mạng xã hội, tải youtube',
                'helper_text' => 'Từ khóa cách nhau bằng dấu phẩy',
            ],
            'copyright_text' => [
                'label' => 'Văn Bản Bản Quyền',
                'placeholder' => 'Bảo lưu mọi quyền.',
            ],
            'copyright_year' => [
                'label' => 'Năm Bản Quyền',
                'helper_text' => 'Năm hiện tại sẽ được sử dụng nếu để trống',
            ],
            'logo_path' => [
                'label' => 'Logo Website',
                'helper_text' => 'Tải lên logo website (JPEG, PNG, SVG - Tối đa 2MB)',
            ],
            'favicon_path' => [
                'label' => 'Favicon',
                'helper_text' => 'Tải lên favicon (ICO, PNG - Tối đa 512KB)',
            ],
            'terms_of_service_url' => [
                'label' => 'URL Điều Khoản Dịch Vụ',
                'placeholder' => 'https://example.com/terms',
            ],
            'privacy_policy_url' => [
                'label' => 'URL Chính Sách Bảo Mật',
                'placeholder' => 'https://example.com/privacy',
            ],
        ],
    ],

    'cookie' => [
        'title' => 'Cài Đặt Cookie',
        'description' => 'Quản lý tệp cấu hình cookie cho việc trích xuất video có xác thực',
        'navigation_label' => 'Cài Đặt Cookie',
        'sections' => [
            'file_configuration' => [
                'title' => 'Cấu Hình Tệp Cookie',
                'description' => 'Tải lên tệp cookie cho việc trích xuất video có xác thực từ các nền tảng yêu cầu đăng nhập',
            ],
        ],
        'fields' => [
            'cookie_file_path' => [
                'label' => 'Tệp Cookie',
                'helper_text' => 'Tải lên tệp .txt chứa cookie cho việc trích xuất video có xác thực. Điều này cho phép tải xuống từ các nền tảng yêu cầu thông tin đăng nhập.',
                'placeholder' => 'Chưa tải lên tệp cookie',
            ],
        ],
    ],
];
