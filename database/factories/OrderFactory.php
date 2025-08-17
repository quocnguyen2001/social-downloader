<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 100.00, 1000.00);
        $discount = fake()->boolean(30) ? fake()->randomFloat(2, 0, $subtotal * 0.2) : 0; // 30% chance of discount, max 20%
        $total = $subtotal - $discount;

        return [
            'id' => Str::uuid(),
            'membership_plan_id' => fake()->boolean(40) ? MembershipPlan::factory() : null,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'status' => fake()->randomElement(['pending', 'processing', 'completed']),
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the order is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
        ]);
    }

    /**
     * Indicate that the order is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the order has a discount.
     */
    public function withDiscount(float $discountAmount = null): static
    {
        return $this->state(function (array $attributes) use ($discountAmount) {
            $subtotal = $attributes['subtotal'] ?? 100.00;
            $discount = $discountAmount ?? fake()->randomFloat(2, 10.00, $subtotal * 0.3);
            
            return [
                'discount' => $discount,
                'total' => $subtotal - $discount,
            ];
        });
    }

    /**
     * Indicate that the order has no discount.
     */
    public function withoutDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $subtotal = $attributes['subtotal'] ?? 100.00;
            
            return [
                'discount' => 0,
                'total' => $subtotal,
            ];
        });
    }
}
