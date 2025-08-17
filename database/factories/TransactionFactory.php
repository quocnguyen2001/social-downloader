<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $paymentMethods = [
            'paypal',
            'stripe',
            'bank_transfer',
            'credit_card',
            'debit_card',
            'apple_pay',
            'google_pay',
        ];

        $currencies = ['USD', 'VND', 'EUR', 'GBP'];
        $statuses = ['pending', 'completed', 'failed', 'cancelled', 'refunded'];

        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->email(),
            'charge_id' => fake()->regexify('[A-Z0-9]{10,20}'),
            'order_id' => fake()->regexify('[A-Z0-9]{8,12}'),
            'payment_method' => fake()->randomElement($paymentMethods),
            'currency' => fake()->randomElement($currencies),
            'payment_logs' => fake()->optional()->randomElements([
                'gateway_response' => fake()->sentence(),
                'transaction_reference' => fake()->uuid(),
                'gateway_fee' => fake()->randomFloat(2, 0, 5),
                'processing_time' => fake()->numberBetween(100, 5000),
            ]),
            'amount' => fake()->randomFloat(2, 1, 999.99),
            'status' => fake()->randomElement($statuses),
        ];
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'charge_id' => fake()->regexify('[A-Z0-9]{10,20}'),
        ]);
    }

    /**
     * Indicate that the transaction failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'payment_logs' => [
                'error_code' => fake()->randomElement(['CARD_DECLINED', 'INSUFFICIENT_FUNDS', 'EXPIRED_CARD']),
                'error_message' => fake()->sentence(),
                'gateway_response' => fake()->sentence(),
            ],
        ]);
    }

    /**
     * Indicate that the transaction was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the transaction was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'charge_id' => fake()->regexify('[A-Z0-9]{10,20}'),
            'payment_logs' => [
                'refund_reason' => fake()->sentence(),
                'refund_amount' => $attributes['amount'] ?? fake()->randomFloat(2, 1, 999.99),
                'refund_date' => fake()->dateTimeBetween('-1 week', 'now')->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Indicate that the transaction uses PayPal.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'paypal',
            'charge_id' => fake()->regexify('[A-Z0-9]{17}'),
            'payment_logs' => [
                'paypal_transaction_id' => fake()->regexify('[A-Z0-9]{17}'),
                'payer_id' => fake()->regexify('[A-Z0-9]{13}'),
                'payment_status' => 'COMPLETED',
            ],
        ]);
    }

    /**
     * Indicate that the transaction uses Stripe.
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'stripe',
            'charge_id' => 'ch_'.fake()->regexify('[a-zA-Z0-9]{24}'),
            'payment_logs' => [
                'stripe_charge_id' => 'ch_'.fake()->regexify('[a-zA-Z0-9]{24}'),
                'stripe_customer_id' => 'cus_'.fake()->regexify('[a-zA-Z0-9]{14}'),
                'card_last4' => fake()->numerify('####'),
                'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            ],
        ]);
    }

    /**
     * Indicate that the transaction is for a specific amount.
     */
    public function amount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    /**
     * Indicate that the transaction is in a specific currency.
     */
    public function currency(string $currency): static
    {
        return $this->state(fn (array $attributes) => [
            'currency' => $currency,
        ]);
    }
}
