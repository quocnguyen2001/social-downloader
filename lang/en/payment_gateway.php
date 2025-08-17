<?php

return [
    'title' => 'Payment Gateway Settings',
    'description' => 'Configure payment methods and gateway settings',

    'tabs' => [
        'bank_transfer' => 'Bank Transfer',
        'paypal' => 'PayPal',
    ],

    'sections' => [
        'bank_transfer_config' => 'Bank Transfer Configuration',
        'bank_transfer_config_description' => 'Configure bank transfer payment method settings',
        'paypal_config' => 'PayPal Configuration',
        'paypal_config_description' => 'Configure PayPal payment gateway settings',
        'general_settings' => 'General Settings',
        'general_settings_description' => 'Enable or disable payment methods',
    ],

    'fields' => [
        // Bank Transfer Fields
        'bank_name' => 'Bank Name',
        'bank_account' => 'Account Holder Name',
        'bank_account_number' => 'Account Number',
        'money_transfer_content_template' => 'Transfer Content Template',
        'api_transactions_api' => 'Transaction API Endpoint',
        'bank_transfer_enabled' => 'Enable Bank Transfer',
        'bank_transfer_currency' => 'Bank Transfer Currency',

        // PayPal Fields
        'paypal_client_id' => 'PayPal Client ID',
        'paypal_client_secret' => 'PayPal Client Secret',
        'paypal_environment' => 'PayPal Environment',
        'paypal_webhook_id' => 'PayPal Webhook ID',
        'paypal_webhook_secret' => 'PayPal Webhook Secret',
        'paypal_enabled' => 'Enable PayPal',
        'paypal_currency' => 'PayPal Currency',
    ],

    'placeholders' => [
        'bank_name' => 'Select a bank from the list',
        'bank_account' => 'Enter account holder name',
        'bank_account_number' => 'Enter bank account number',
        'money_transfer_content_template' => 'Payment for order #{order_id} - {user_name}',
        'api_transactions_api' => 'https://api.example.com/transactions',
        'paypal_client_id' => 'Enter PayPal Client ID',
        'paypal_client_secret' => 'Enter PayPal Client Secret',
        'paypal_webhook_id' => 'Enter PayPal Webhook ID (optional)',
        'paypal_webhook_secret' => 'Enter PayPal Webhook Secret (optional)',
    ],

    'help' => [
        'bank_name' => 'Select the bank where payments will be received',
        'bank_account' => 'Full name of the account holder as registered with the bank',
        'bank_account_number' => 'The bank account number for receiving payments',
        'money_transfer_content_template' => 'Template for transfer description. Use {order_id} and {user_name} as placeholders',
        'api_transactions_api' => 'API endpoint for verifying bank transfer transactions',
        'bank_transfer_enabled' => 'Enable bank transfer as a payment method',
        'bank_transfer_currency' => 'Currency for bank transfer payments',
        'paypal_client_id' => 'Your PayPal application Client ID',
        'paypal_client_secret' => 'Your PayPal application Client Secret',
        'paypal_environment' => 'Use Sandbox for testing, Live for production',
        'paypal_webhook_id' => 'PayPal Webhook ID for payment notifications (optional)',
        'paypal_webhook_secret' => 'PayPal Webhook Secret for signature verification (optional)',
        'paypal_enabled' => 'Enable PayPal as a payment method',
        'paypal_currency' => 'Currency for PayPal payments',
    ],

    'options' => [
        'paypal_environment' => [
            'sandbox' => 'Sandbox (Testing)',
            'live' => 'Live (Production)',
        ],
        'currencies' => [
            'paypal' => [
                'USD' => 'US Dollar (USD)',
                'EUR' => 'Euro (EUR)',
                'GBP' => 'British Pound (GBP)',
                'CAD' => 'Canadian Dollar (CAD)',
                'AUD' => 'Australian Dollar (AUD)',
                'JPY' => 'Japanese Yen (JPY)',
            ],
            'bank_transfer' => [
                'VND' => 'Vietnamese Dong (VND)',
                'USD' => 'US Dollar (USD)',
            ],
        ],
    ],

    'actions' => [
        'test_connection' => 'Test Connection',
        'refresh_banks' => 'Refresh Bank List',
        'clear_cache' => 'Clear Cache',
        'save' => 'Save Settings',
    ],

    'messages' => [
        'success' => [
            'settings_saved' => 'Payment gateway settings saved successfully',
            'connection_test_passed' => 'Connection test passed successfully',
            'bank_list_refreshed' => 'Bank list refreshed successfully',
            'cache_cleared' => 'Cache cleared successfully',
        ],
        'error' => [
            'settings_save_failed' => 'Failed to save payment gateway settings',
            'connection_test_failed' => 'Connection test failed',
            'bank_list_refresh_failed' => 'Failed to refresh bank list',
            'cache_clear_failed' => 'Failed to clear cache',
            'invalid_configuration' => 'Invalid configuration',
        ],
        'warning' => [
            'no_payment_methods_enabled' => 'No payment methods are currently enabled',
            'incomplete_configuration' => 'Payment method configuration is incomplete',
        ],
        'info' => [
            'bank_list_cached' => 'Bank list is cached and will be refreshed automatically',
            'test_mode_warning' => 'PayPal is in test mode. Switch to Live for production',
        ],
    ],

    'validation' => [
        'bank_name_required' => 'Bank name is required when bank transfer is enabled',
        'bank_account_required' => 'Account holder name is required when bank transfer is enabled',
        'bank_account_number_required' => 'Account number is required when bank transfer is enabled',
        'paypal_client_id_required' => 'PayPal Client ID is required when PayPal is enabled',
        'paypal_client_secret_required' => 'PayPal Client Secret is required when PayPal is enabled',
        'invalid_api_url' => 'Invalid API URL format',
        'invalid_currency' => 'Invalid currency code',
        'invalid_payment_method' => 'The selected payment method is not available',
        'no_payment_methods_enabled' => 'No payment methods are currently enabled',
    ],

    'status' => [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'configured' => 'Configured',
        'not_configured' => 'Not Configured',
        'testing' => 'Testing',
        'production' => 'Production',
    ],
];
