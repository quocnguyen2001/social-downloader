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

    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',

    'messages' => [
        'registration_success' => 'User registered successfully.',
        'registration_error' => 'Registration failed. Please try again.',
        'login_success' => 'Login successful.',
        'login_failed' => 'Invalid credentials provided.',
        'login_error' => 'Login failed. Please try again.',
        'logout_success' => 'Logged out successfully.',
        'password_reset_sent' => 'Password reset link sent to your email.',
        'password_reset_failed' => 'Failed to send password reset link.',
        'password_reset_success' => 'Password reset successfully.',
        'password_reset_error' => 'Password reset failed. Please try again.',
        'token_invalid' => 'Invalid or expired token.',
        'user_not_found' => 'User not found.',
        'email_not_verified' => 'Please verify your email address.',
        'account_disabled' => 'Your account has been disabled.',
        'rate_limit_exceeded' => 'Too many requests. Please try again later.',
    ],

    'validation' => [
        'name_required' => 'Name is required.',
        'email_required' => 'Email address is required.',
        'email_invalid' => 'Please provide a valid email address.',
        'email_exists' => 'This email address is already registered.',
        'password_required' => 'Password is required.',
        'password_confirmation' => 'Password confirmation does not match.',
        'password_min' => 'Password must be at least 8 characters long.',
        'password_format' => 'Password must contain uppercase, lowercase, numbers, and symbols.',
        'registration_failed' => 'Registration validation failed.',
        'login_failed' => 'Login validation failed.',
        'password_reset_failed' => 'Password reset validation failed.',
        'current_password_incorrect' => 'Current password is incorrect.',
        'token_required' => 'Reset token is required.',
        'token_invalid' => 'Invalid or expired reset token.',
    ],

    'emails' => [
        'welcome' => [
            'subject' => 'Welcome to :app_name',
            'greeting' => 'Welcome, :name!',
            'intro' => 'Thank you for registering with :app_name. Your account has been created successfully.',
            'features' => 'With your account, you can:',
            'feature_1' => 'Access our powerful video extraction tools',
            'feature_2' => 'Manage your API tokens and usage',
            'feature_3' => 'Track your download history',
            'feature_4' => 'Get priority support',
            'button_text' => 'Get Started',
            'outro' => 'If you have any questions, feel free to contact our support team.',
            'account_details' => 'Your account details:',
            'email_label' => 'Email:',
            'registered_label' => 'Registered:',
        ],
        'password_reset' => [
            'subject' => 'Reset Your Password',
            'greeting' => 'Hello, :name!',
            'intro' => 'You are receiving this email because we received a password reset request for your account.',
            'button_text' => 'Reset Password',
            'security_note' => 'Security Note:',
            'outro' => 'This password reset link will expire in :count minutes. If you did not request a password reset, no further action is required.',
            'manual_copy' => 'If the button above doesn\'t work, copy and paste this link into your browser:',
            'ignore_note' => 'If you did not request a password reset, please ignore this email or contact support if you have concerns.',
        ],
        'password_changed' => [
            'subject' => 'Password Changed Successfully',
            'greeting' => 'Hello, :name!',
            'intro' => 'Your password has been changed successfully.',
            'success_title' => 'Password Updated Successfully',
            'success_message' => 'Your password has been successfully changed on',
            'security_tips' => 'For your security, we recommend:',
            'tip_1' => 'Keep your password confidential',
            'tip_2' => 'Use a unique password for this account',
            'tip_3' => 'Enable two-factor authentication if available',
            'tip_4' => 'Log out of all devices if you suspect unauthorized access',
            'button_text' => 'Access Your Account',
            'security_alert' => 'Security Alert:',
            'outro' => 'If you did not make this change, please contact our support team immediately.',
            'contact_support' => 'Contact support immediately:',
        ],
        'footer' => '© :year :app_name. All rights reserved.',
        'support' => 'Need help?',
        'contact_support' => 'Contact Support',
    ],

    'permissions' => [
        'insufficient' => 'Insufficient permissions to perform this action.',
        'token_expired' => 'Your session has expired. Please login again.',
        'unauthorized' => 'Unauthorized access.',
    ],

    'fields' => [
        'name' => 'Name',
        'email' => 'Email',
        'password' => 'Password',
        'device_name' => 'Device Name',
        'token' => 'Token',
    ],
];
