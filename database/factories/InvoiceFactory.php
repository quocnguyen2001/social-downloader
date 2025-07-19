<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
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
        $youtubeRequests = fake()->numberBetween(0, $totalRequests);
        $remaining = $totalRequests - $youtubeRequests;
        $tiktokRequests = fake()->numberBetween(0, $remaining);
        $remaining -= $tiktokRequests;
        $instagramRequests = fake()->numberBetween(0, $remaining);
        $facebookRequests = $remaining - $instagramRequests;

        $invoiceSent = fake()->boolean(70); // 70% chance invoice is sent
        $isPaid = $invoiceSent ? fake()->boolean(60) : false; // 60% of sent invoices are paid

        return [
            'id' => Str::uuid(),
            'api_key_id' => ApiKey::factory(),
            'membership_plan_id' => fake()->boolean(30) ? MembershipPlan::factory() : null,
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
     * Indicate that the invoice is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid' => true,
            'paid_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'payment_method' => fake()->randomElement(['bank_transfer', 'credit_card', 'paypal', 'crypto']),
            'invoice_sent' => true,
            'invoice_sent_at' => fake()->dateTimeBetween('-2 months', '-1 month'),
        ]);
    }

    /**
     * Indicate that the invoice is unpaid.
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
     * Indicate that the invoice has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_sent' => true,
            'invoice_sent_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ]);
    }

    /**
     * Indicate that the invoice has not been sent.
     */
    public function notSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_sent' => false,
            'invoice_sent_at' => null,
        ]);
    }
}
