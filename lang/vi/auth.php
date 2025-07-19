<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used during authentication for various
    | messages that we need to display to the user. You are free to modify
    | these language lines according to your application's requirements.
    |
    */

    'failed' => 'Thông tin đăng nhập không khớp với hồ sơ của chúng tôi.',
    'password' => 'Mật khẩu được cung cấp không chính xác.',
    'throttle' => 'Quá nhiều lần đăng nhập. Vui lòng thử lại sau :seconds giây.',

    'messages' => [
        'registration_success' => 'Đăng ký người dùng thành công.',
        'registration_error' => 'Đăng ký thất bại. Vui lòng thử lại.',
        'login_success' => 'Đăng nhập thành công.',
        'login_failed' => 'Thông tin đăng nhập không hợp lệ.',
        'login_error' => 'Đăng nhập thất bại. Vui lòng thử lại.',
        'logout_success' => 'Đăng xuất thành công.',
        'password_reset_sent' => 'Liên kết đặt lại mật khẩu đã được gửi đến email của bạn.',
        'password_reset_failed' => 'Không thể gửi liên kết đặt lại mật khẩu.',
        'password_reset_success' => 'Đặt lại mật khẩu thành công.',
        'password_reset_error' => 'Đặt lại mật khẩu thất bại. Vui lòng thử lại.',
        'token_invalid' => 'Token không hợp lệ hoặc đã hết hạn.',
        'user_not_found' => 'Không tìm thấy người dùng.',
        'email_not_verified' => 'Vui lòng xác minh địa chỉ email của bạn.',
        'account_disabled' => 'Tài khoản của bạn đã bị vô hiệu hóa.',
        'rate_limit_exceeded' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
    ],

    'validation' => [
        'name_required' => 'Tên là bắt buộc.',
        'email_required' => 'Địa chỉ email là bắt buộc.',
        'email_invalid' => 'Vui lòng cung cấp địa chỉ email hợp lệ.',
        'email_exists' => 'Địa chỉ email này đã được đăng ký.',
        'password_required' => 'Mật khẩu là bắt buộc.',
        'password_confirmation' => 'Xác nhận mật khẩu không khớp.',
        'password_min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        'password_format' => 'Mật khẩu phải chứa chữ hoa, chữ thường, số và ký hiệu.',
        'registration_failed' => 'Xác thực đăng ký thất bại.',
        'login_failed' => 'Xác thực đăng nhập thất bại.',
        'password_reset_failed' => 'Xác thực đặt lại mật khẩu thất bại.',
        'current_password_incorrect' => 'Mật khẩu hiện tại không chính xác.',
        'token_required' => 'Token đặt lại là bắt buộc.',
        'token_invalid' => 'Token đặt lại không hợp lệ hoặc đã hết hạn.',
    ],

    'emails' => [
        'welcome' => [
            'subject' => 'Chào mừng đến với :app_name',
            'greeting' => 'Chào mừng, :name!',
            'intro' => 'Cảm ơn bạn đã đăng ký với :app_name. Tài khoản của bạn đã được tạo thành công.',
            'features' => 'Với tài khoản của bạn, bạn có thể:',
            'feature_1' => 'Truy cập các công cụ trích xuất video mạnh mẽ',
            'feature_2' => 'Quản lý token API và mức sử dụng',
            'feature_3' => 'Theo dõi lịch sử tải xuống',
            'feature_4' => 'Nhận hỗ trợ ưu tiên',
            'button_text' => 'Bắt Đầu',
            'outro' => 'Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với đội ngũ hỗ trợ của chúng tôi.',
            'account_details' => 'Chi tiết tài khoản của bạn:',
            'email_label' => 'Email:',
            'registered_label' => 'Đã đăng ký:',
        ],
        'password_reset' => [
            'subject' => 'Đặt Lại Mật Khẩu',
            'greeting' => 'Xin chào, :name!',
            'intro' => 'Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.',
            'button_text' => 'Đặt Lại Mật Khẩu',
            'security_note' => 'Lưu ý bảo mật:',
            'outro' => 'Liên kết đặt lại mật khẩu này sẽ hết hạn sau :count phút. Nếu bạn không yêu cầu đặt lại mật khẩu, không cần thực hiện thêm hành động nào.',
            'manual_copy' => 'Nếu nút ở trên không hoạt động, hãy sao chép và dán liên kết này vào trình duyệt của bạn:',
            'ignore_note' => 'Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này hoặc liên hệ hỗ trợ nếu bạn có lo ngại.',
        ],
        'password_changed' => [
            'subject' => 'Mật Khẩu Đã Được Thay Đổi Thành Công',
            'greeting' => 'Xin chào, :name!',
            'intro' => 'Mật khẩu của bạn đã được thay đổi thành công.',
            'success_title' => 'Mật Khẩu Đã Được Cập Nhật Thành Công',
            'success_message' => 'Mật khẩu của bạn đã được thay đổi thành công vào',
            'security_tips' => 'Để bảo mật, chúng tôi khuyên bạn:',
            'tip_1' => 'Giữ bí mật mật khẩu của bạn',
            'tip_2' => 'Sử dụng mật khẩu duy nhất cho tài khoản này',
            'tip_3' => 'Bật xác thực hai yếu tố nếu có sẵn',
            'tip_4' => 'Đăng xuất khỏi tất cả thiết bị nếu bạn nghi ngờ truy cập trái phép',
            'button_text' => 'Truy Cập Tài Khoản',
            'security_alert' => 'Cảnh Báo Bảo Mật:',
            'outro' => 'Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ với đội ngũ hỗ trợ của chúng tôi ngay lập tức.',
            'contact_support' => 'Liên hệ hỗ trợ ngay lập tức:',
        ],
        'footer' => '© :year :app_name. Tất cả quyền được bảo lưu.',
        'support' => 'Cần trợ giúp?',
        'contact_support' => 'Liên Hệ Hỗ Trợ',
    ],

    'permissions' => [
        'insufficient' => 'Không đủ quyền để thực hiện hành động này.',
        'token_expired' => 'Phiên của bạn đã hết hạn. Vui lòng đăng nhập lại.',
        'unauthorized' => 'Truy cập không được phép.',
    ],

    'fields' => [
        'name' => 'Tên',
        'email' => 'Email',
        'password' => 'Mật khẩu',
        'device_name' => 'Tên thiết bị',
        'token' => 'Token',
    ],
];
