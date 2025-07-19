<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MonthlyBilling>
 */
class MonthlyBillingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalRequests = fake()->numberBetween(100, 5000);
        $totalCost = fake()->randomFloat(2, 50.00, 500.00);

        // Distribute requests across platforms
        $youtubeRequests = fake()->numberBetween(0, intval($totalRequests * 0.5));
        $tiktokRequests = fake()->numberBetween(0, intval(($totalRequests - $youtubeRequests) * 0.6));
        $instagramRequests = fake()->numberBetween(0, intval(($totalRequests - $youtubeRequests - $tiktokRequests) * 0.7));
        $facebookRequests = $totalRequests - $youtubeRequests - $tiktokRequests - $instagramRequests;

        $isPaid = fake()->boolean(70); // 70% chance of being paid
        $invoiceSent = fake()->boolean(90); // 90% chance invoice was sent

        return [
            'id' => Str::uuid(),
            'api_key_id' => ApiKey::factory(),
            'billing_month' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-01'),
            'total_requests' => $totalRequests,
            'total_cost' => $totalCost,
            'youtube_requests' => $youtubeRequests,
            'tiktok_requests' => $tiktokRequests,
            'instagram_requests' => $instagramRequests,
            'facebook_requests' => $facebookRequests,
            'invoice_sent' => $invoiceSent,
            'invoice_sent_at' => $invoiceSent ? fake()->dateTimeBetween('-2 months', 'now') : null,
            'paid' => $isPaid,
            'paid_at' => $isPaid ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'payment_method' => $isPaid ? fake()->randomElement(['bank_transfer', 'credit_card', 'paypal', 'crypto']) : null,
        ];
    }

    /**
     * Indicate that the billing is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => true,
            'paid_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'payment_method' => fake()->randomElement(['bank_transfer', 'credit_card', 'paypal', 'crypto']),
            'invoice_sent' => true,
            'invoice_sent_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    /**
     * Indicate that the billing is unpaid.
     */
    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => false,
            'paid_at' => null,
            'payment_method' => null,
        ]);
    }

    /**
     * Indicate that the invoice was sent.
     */
    public function invoiceSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_sent' => true,
            'invoice_sent_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }
}
