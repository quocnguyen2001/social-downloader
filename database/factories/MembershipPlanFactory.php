<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MembershipPlan>
 */
class MembershipPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['youtube', 'tiktok', 'instagram', 'facebook'];
        $qualities = ['144p', '360p', '720p', '1080p'];
        $formats = ['mp4', 'mp3', 'webm'];

        $planNames = [
            'Free',
            'Basic',
            'Pro',
            'Premium',
            'Enterprise',
            'Starter',
            'Professional',
            'Business',
        ];

        $name = fake()->randomElement($planNames);
        $slug = Str::slug($name.'-'.fake()->randomNumber(3));

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => fake()->sentence(10),
            'price' => fake()->randomFloat(2, 0, 99.99),
            'currency' => fake()->randomElement(['USD', 'VND', 'EUR']),
            'billing_cycle' => fake()->randomElement(['monthly', 'yearly', 'lifetime']),

            // Request limits
            'daily_request_limit' => fake()->randomElement([0, 100, 500, 1000, 5000, 10000]),
            'total_request_download' => fake()->randomElement([0, 5000, 25000, 50000, 250000, 500000]),

            // Features
            'allowed_platforms' => fake()->randomElements($platforms, fake()->numberBetween(1, 4)),
            'allowed_qualities' => fake()->randomElements($qualities, fake()->numberBetween(2, 4)),
            'allowed_formats' => fake()->randomElements($formats, fake()->numberBetween(1, 3)),

            // Additional features
            'priority_processing' => fake()->boolean(30),

            // Plan status and ordering
            'is_active' => fake()->boolean(80),
            'is_featured' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the membership plan is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Free',
            'slug' => 'free',
            'price' => 0.00,
            'daily_request_limit' => 100,
            'total_request_download' => 3000,
            'priority_processing' => false,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    /**
     * Indicate that the membership plan is basic.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 9.99,
            'daily_request_limit' => 500,
            'total_request_download' => 15000,
            'priority_processing' => false,
            'is_active' => true,
            'is_featured' => false,
        ]);
    }

    /**
     * Indicate that the membership plan is pro.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'slug' => 'pro',
            'price' => 19.99,
            'daily_request_limit' => 1000,
            'total_request_download' => 30000,
            'priority_processing' => true,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the membership plan is premium.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Premium',
            'slug' => 'premium',
            'price' => 49.99,
            'daily_request_limit' => 0, // unlimited
            'total_request_download' => 0, // unlimited
            'priority_processing' => true,
            'is_active' => true,
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the membership plan is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the membership plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the membership plan is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    /**
     * Indicate that the membership plan has unlimited requests.
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'daily_request_limit' => 0,
            'total_request_download' => 0,
        ]);
    }
}
