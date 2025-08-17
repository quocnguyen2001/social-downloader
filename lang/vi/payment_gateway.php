<?php

return [
    'title' => 'Cài Đặt Cổng Thanh Toán',
    'description' => 'Cấu hình các phương thức thanh toán và cài đặt cổng thanh toán',

    'tabs' => [
        'bank_transfer' => 'Chuyển Khoản Ngân Hàng',
        'paypal' => 'PayPal',
    ],

    'sections' => [
        'bank_transfer_config' => 'Cấu Hình Chuyển Khoản Ngân Hàng',
        'bank_transfer_config_description' => 'Cấu hình cài đặt phương thức thanh toán chuyển khoản ngân hàng',
        'paypal_config' => 'Cấu Hình PayPal',
        'paypal_config_description' => 'Cấu hình cài đặt cổng thanh toán PayPal',
        'general_settings' => 'Cài Đặt Chung',
        'general_settings_description' => 'Bật hoặc tắt các phương thức thanh toán',
    ],

    'fields' => [
        // Bank Transfer Fields
        'bank_name' => 'Tên Ngân Hàng',
        'bank_account' => 'Tên Chủ Tài Khoản',
        'bank_account_number' => 'Số Tài Khoản',
        'money_transfer_content_template' => 'Mẫu Nội Dung Chuyển Khoản',
        'api_transactions_api' => 'API Kiểm Tra Giao Dịch',
        'bank_transfer_enabled' => 'Bật Chuyển Khoản Ngân Hàng',
        'bank_transfer_currency' => 'Tiền Tệ Chuyển Khoản',

        // PayPal Fields
        'paypal_client_id' => 'PayPal Client ID',
        'paypal_client_secret' => 'PayPal Client Secret',
        'paypal_environment' => 'Môi Trường PayPal',
        'paypal_webhook_id' => 'PayPal Webhook ID',
        'paypal_webhook_secret' => 'PayPal Webhook Secret',
        'paypal_enabled' => 'Bật PayPal',
        'paypal_currency' => 'Tiền Tệ PayPal',
    ],

    'placeholders' => [
        'bank_name' => 'Chọn ngân hàng từ danh sách',
        'bank_account' => 'Nhập tên chủ tài khoản',
        'bank_account_number' => 'Nhập số tài khoản ngân hàng',
        'money_transfer_content_template' => 'Thanh toán đơn hàng #{order_id} - {user_name}',
        'api_transactions_api' => 'https://api.example.com/transactions',
        'paypal_client_id' => 'Nhập PayPal Client ID',
        'paypal_client_secret' => 'Nhập PayPal Client Secret',
        'paypal_webhook_id' => 'Nhập PayPal Webhook ID (tùy chọn)',
        'paypal_webhook_secret' => 'Nhập PayPal Webhook Secret (tùy chọn)',
    ],

    'help' => [
        'bank_name' => 'Chọn ngân hàng nơi sẽ nhận thanh toán',
        'bank_account' => 'Họ tên đầy đủ của chủ tài khoản như đã đăng ký với ngân hàng',
        'bank_account_number' => 'Số tài khoản ngân hàng để nhận thanh toán',
        'money_transfer_content_template' => 'Mẫu nội dung chuyển khoản. Sử dụng {order_id} và {user_name} làm biến thay thế',
        'api_transactions_api' => 'API endpoint để xác minh giao dịch chuyển khoản ngân hàng',
        'bank_transfer_enabled' => 'Bật chuyển khoản ngân hàng làm phương thức thanh toán',
        'bank_transfer_currency' => 'Tiền tệ cho thanh toán chuyển khoản ngân hàng',
        'paypal_client_id' => 'PayPal Client ID của ứng dụng',
        'paypal_client_secret' => 'PayPal Client Secret của ứng dụng',
        'paypal_environment' => 'Sử dụng Sandbox để test, Live cho production',
        'paypal_webhook_id' => 'PayPal Webhook ID cho thông báo thanh toán (tùy chọn)',
        'paypal_webhook_secret' => 'PayPal Webhook Secret để xác minh chữ ký (tùy chọn)',
        'paypal_enabled' => 'Bật PayPal làm phương thức thanh toán',
        'paypal_currency' => 'Tiền tệ cho thanh toán PayPal',
    ],

    'options' => [
        'paypal_environment' => [
            'sandbox' => 'Sandbox (Thử nghiệm)',
            'live' => 'Live (Sản xuất)',
        ],
        'currencies' => [
            'paypal' => [
                'USD' => 'Đô la Mỹ (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'Bảng Anh (GBP)',
                'CAD' => 'Đô la Canada (CAD)',
                'AUD' => 'Đô la Úc (AUD)',
                'JPY' => 'Yên Nhật (JPY)',
            ],
            'bank_transfer' => [
                'VND' => 'Việt Nam Đồng (VND)',
                'USD' => 'Đô la Mỹ (USD)',
            ],
        ],
    ],

    'actions' => [
        'test_connection' => 'Kiểm Tra Kết Nối',
        'refresh_banks' => 'Làm Mới Danh Sách Ngân Hàng',
        'clear_cache' => 'Xóa Cache',
        'save' => 'Lưu Cài Đặt',
    ],

    'messages' => [
        'success' => [
            'settings_saved' => 'Cài đặt cổng thanh toán đã được lưu thành công',
            'connection_test_passed' => 'Kiểm tra kết nối thành công',
            'bank_list_refreshed' => 'Danh sách ngân hàng đã được làm mới thành công',
            'cache_cleared' => 'Cache đã được xóa thành công',
        ],
        'error' => [
            'settings_save_failed' => 'Không thể lưu cài đặt cổng thanh toán',
            'connection_test_failed' => 'Kiểm tra kết nối thất bại',
            'bank_list_refresh_failed' => 'Không thể làm mới danh sách ngân hàng',
            'cache_clear_failed' => 'Không thể xóa cache',
            'invalid_configuration' => 'Cấu hình không hợp lệ',
        ],
        'warning' => [
            'no_payment_methods_enabled' => 'Hiện tại không có phương thức thanh toán nào được bật',
            'incomplete_configuration' => 'Cấu hình phương thức thanh toán chưa hoàn tất',
        ],
        'info' => [
            'bank_list_cached' => 'Danh sách ngân hàng được lưu cache và sẽ tự động làm mới',
            'test_mode_warning' => 'PayPal đang ở chế độ test. Chuyển sang Live cho production',
        ],
    ],

    'validation' => [
        'bank_name_required' => 'Tên ngân hàng là bắt buộc khi bật chuyển khoản ngân hàng',
        'bank_account_required' => 'Tên chủ tài khoản là bắt buộc khi bật chuyển khoản ngân hàng',
        'bank_account_number_required' => 'Số tài khoản là bắt buộc khi bật chuyển khoản ngân hàng',
        'paypal_client_id_required' => 'PayPal Client ID là bắt buộc khi bật PayPal',
        'paypal_client_secret_required' => 'PayPal Client Secret là bắt buộc khi bật PayPal',
        'invalid_api_url' => 'Định dạng URL API không hợp lệ',
        'invalid_currency' => 'Mã tiền tệ không hợp lệ',
        'invalid_payment_method' => 'Phương thức thanh toán được chọn không khả dụng',
        'no_payment_methods_enabled' => 'Hiện tại không có phương thức thanh toán nào được bật',
    ],

    'status' => [
        'enabled' => 'Đã Bật',
        'disabled' => 'Đã Tắt',
        'configured' => 'Đã Cấu Hình',
        'not_configured' => 'Chưa Cấu Hình',
        'testing' => 'Thử Nghiệm',
        'production' => 'Sản Xuất',
    ],
];
