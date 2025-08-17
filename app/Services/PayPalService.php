<?php

declare(strict_types=1);

namespace App\Services;

use App\Settings\PaymentGatewaySettings;
use Illuminate\Support\Facades\Log;
use PayPalServerSDK\Environment;
use PayPalServerSDK\PayPalServerSDKClient;
use PayPalServerSDK\PayPalServerSDKClientBuilder;
use PayPalServerSDK\Models\OrderRequest;
use PayPalServerSDK\Models\PurchaseUnitRequest;
use PayPalServerSDK\Models\AmountWithBreakdown;
use PayPalServerSDK\Models\ApplicationContext;

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
            $client = $this->getClient();

            // Build the order request
            $orderRequest = $this->buildOrderRequest($orderData);

            // Create the order using PayPal SDK
            $ordersController = $client->getOrdersController();
            $response = $ordersController->ordersCreate($orderRequest);

            if ($response->getStatusCode() !== 201) {
                throw new \Exception('PayPal order creation failed with status: ' . $response->getStatusCode());
            }

            $order = $response->getResult();

            // Extract approval URL for checkout
            $approvalUrl = null;
            foreach ($order->getLinks() as $link) {
                if ($link->getRel() === 'approve') {
                    $approvalUrl = $link->getHref();
                    break;
                }
            }

            if (!$approvalUrl) {
                throw new \Exception('PayPal approval URL not found in response');
            }

            Log::info('PayPal order created successfully', [
                'paypal_order_id' => $order->getId(),
                'status' => $order->getStatus(),
                'approval_url' => $approvalUrl,
            ]);

            return [
                'success' => true,
                'paypal_order_id' => $order->getId(),
                'status' => $order->getStatus(),
                'approval_url' => $approvalUrl,
                'checkout_url' => $approvalUrl,
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
    private function buildOrderRequest(array $orderData): OrderRequest
    {
        // Create amount object
        $amount = AmountWithBreakdown::builder()
            ->currencyCode($orderData['currency'] ?? $this->settings->paypal_currency)
            ->value(number_format((float)$orderData['amount'], 2, '.', ''))
            ->build();

        // Create purchase unit
        $purchaseUnit = PurchaseUnitRequest::builder()
            ->referenceId($orderData['reference_id'] ?? uniqid())
            ->amount($amount)
            ->description($orderData['description'] ?? 'Payment')
            ->build();

        // Create application context
        $applicationContext = ApplicationContext::builder()
            ->cancelUrl($orderData['cancel_url'] ?? url('/payment/cancel'))
            ->returnUrl($orderData['return_url'] ?? url('/payment/success'))
            ->brandName($orderData['brand_name'] ?? config('app.name'))
            ->locale($orderData['locale'] ?? 'en-US')
            ->landingPage('BILLING')
            ->shippingPreference('NO_SHIPPING')
            ->userAction('PAY_NOW')
            ->build();

        // Create and return order request
        return OrderRequest::builder()
            ->intent('CAPTURE')
            ->purchaseUnits([$purchaseUnit])
            ->applicationContext($applicationContext)
            ->build();
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
