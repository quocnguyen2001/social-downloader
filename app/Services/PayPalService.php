<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\PaymentGatewaySettings;
use Illuminate\Support\Facades\Log;
use PayPalServerSDK\Environment;
use PayPalServerSDK\PayPalServerSDKClient;
use PayPalServerSDK\PayPalServerSDKClientBuilder;

/**
 * PayPal Service Wrapper.
 *
 * This service handles PayPal SDK integration for payment processing
 * with proper configuration and error handling.
 */
class PayPalService
{
    /**
     * PayPal SDK client instance.
     */
    private ?PayPalServerSDKClient $client = null;

    /**
     * Payment gateway settings instance.
     */
    private PaymentGatewaySettings $settings;

    /**
     * Constructor.
     */
    public function __construct(PaymentGatewaySettings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Get PayPal SDK client.
     *
     * @throws \Exception
     */
    private function getClient(): PayPalServerSDKClient
    {
        if ($this->client === null) {
            $this->client = $this->createClient();
        }

        return $this->client;
    }

    /**
     * Create PayPal SDK client.
     *
     * @throws \Exception
     */
    private function createClient(): PayPalServerSDKClient
    {
        if (! $this->settings->isPayPalConfigured()) {
            throw new \Exception('PayPal is not properly configured');
        }

        $environment = $this->settings->paypal_environment === 'live'
            ? Environment::PRODUCTION
            : Environment::SANDBOX;

        return PayPalServerSDKClientBuilder::init()
            ->clientCredentialsAuth(
                $this->settings->paypal_client_id,
                $this->settings->paypal_client_secret
            )
            ->environment($environment)
            ->build();
    }

    /**
     * Create a PayPal order.
     *
     * @throws \Exception
     */
    public function createOrder(array $orderData): array
    {
        try {
            // For now, return a placeholder implementation
            // This will be implemented when actual payment processing is needed
            return [
                'success' => true,
                'order_id' => 'test_order_'.uniqid(),
                'status' => 'CREATED',
                'message' => 'PayPal order creation placeholder - implement when needed',
            ];

        } catch (\Exception $e) {
            Log::error('PayPal order creation failed', [
                'error' => $e->getMessage(),
                'order_data' => $orderData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture a PayPal order.
     *
     * @throws \Exception
     */
    public function captureOrder(string $orderId): array
    {
        // Placeholder implementation
        return [
            'success' => true,
            'order_id' => $orderId,
            'status' => 'COMPLETED',
            'message' => 'PayPal order capture placeholder - implement when needed',
        ];
    }

    /**
     * Get PayPal order details.
     *
     * @throws \Exception
     */
    public function getOrder(string $orderId): array
    {
        // Placeholder implementation
        return [
            'success' => true,
            'order' => [
                'id' => $orderId,
                'status' => 'CREATED',
            ],
            'message' => 'PayPal get order placeholder - implement when needed',
        ];
    }

    /**
     * Build PayPal order request body.
     */
    private function buildOrderRequest(array $orderData): array
    {
        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $orderData['reference_id'] ?? uniqid(),
                    'amount' => [
                        'currency_code' => $orderData['currency'] ?? $this->settings->paypal_currency,
                        'value' => $orderData['amount'],
                    ],
                    'description' => $orderData['description'] ?? 'Payment',
                ],
            ],
            'application_context' => [
                'cancel_url' => $orderData['cancel_url'] ?? url('/payment/cancel'),
                'return_url' => $orderData['return_url'] ?? url('/payment/success'),
                'brand_name' => $orderData['brand_name'] ?? config('app.name'),
                'locale' => $orderData['locale'] ?? 'en-US',
                'landing_page' => 'BILLING',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'PAY_NOW',
            ],
        ];
    }

    /**
     * Validate PayPal configuration.
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        if (empty($this->settings->paypal_client_id)) {
            $errors[] = 'PayPal Client ID is required';
        }

        if (empty($this->settings->paypal_client_secret)) {
            $errors[] = 'PayPal Client Secret is required';
        }

        if (! in_array($this->settings->paypal_environment, ['sandbox', 'live'])) {
            $errors[] = 'PayPal Environment must be either sandbox or live';
        }

        if (! in_array($this->settings->paypal_currency, ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'])) {
            $errors[] = 'PayPal Currency is not supported';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Test PayPal connection.
     */
    public function testConnection(): array
    {
        try {
            $validation = $this->validateConfiguration();
            if (! $validation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Configuration validation failed',
                    'details' => $validation['errors'],
                ];
            }

            // For now, just validate configuration
            // Actual connection testing will be implemented when needed
            return [
                'success' => true,
                'message' => 'PayPal configuration is valid',
                'environment' => $this->settings->paypal_environment,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection test failed',
                'details' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get PayPal environment info.
     */
    public function getEnvironmentInfo(): array
    {
        return [
            'environment' => $this->settings->paypal_environment,
            'currency' => $this->settings->paypal_currency,
            'enabled' => $this->settings->paypal_enabled,
            'configured' => $this->settings->isPayPalConfigured(),
        ];
    }
}
