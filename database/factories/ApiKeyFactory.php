<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
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

        $companyNames = app()->getLocale() === 'vi' ? [
            'Công ty TNHH Công nghệ ABC',
            'Công ty Cổ phần Phần mềm XYZ',
            'Công ty TNHH Giải pháp số DEF',
            'Công ty Cổ phần Truyền thông GHI',
            'Công ty TNHH Phát triển ứng dụng JKL',
        ] : [
            'TechCorp Solutions',
            'Digital Innovations Ltd',
            'StreamTech Inc',
            'VideoBot Solutions',
            'MediaFlow Technologies',
        ];

        return [
            'id' => Str::uuid(),
            'user_id' => fake()->optional(0.8)->randomElement([
                User::factory(),
                null,
            ]),
            'name' => fake()->randomElement($companyNames).' App',
            'key_hash' => hash('sha256', 'vd_live_'.Str::random(32)),
            'key_prefix' => 'vd_live_',
            'status' => fake()->randomElement(['active', 'inactive', 'suspended']),
            'tier' => fake()->randomElement(['basic', 'pro', 'premium']),
            'price_per_request' => fake()->randomFloat(4, 0.0100, 0.1000),
            'daily_limit' => fake()->randomElement([500, 1000, 2000, 5000]),
            'monthly_limit' => fake()->randomElement([15000, 30000, 60000, 150000]),
            'daily_usage' => fake()->numberBetween(0, 500),
            'monthly_usage' => fake()->numberBetween(0, 15000),
            'total_usage' => fake()->numberBetween(0, 100000),
            'last_reset_daily' => now()->toDateString(),
            'last_reset_monthly' => now()->startOfMonth()->toDateString(),
            'contact_email' => fake()->companyEmail(),
            'billing_email' => fake()->optional()->companyEmail(),
            'company_name' => fake()->company(),
            'webhook_url' => fake()->optional()->url(),
            'allowed_platforms' => fake()->randomElements($platforms, fake()->numberBetween(1, 4)),
            'allowed_qualities' => fake()->randomElements($qualities, fake()->numberBetween(2, 4)),
            'allowed_formats' => fake()->randomElements($formats, fake()->numberBetween(1, 3)),
        ];
    }

    /**
     * Indicate that the API key is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the API key is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the API key is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the API key is basic tier.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => 'basic',
        ]);
    }

    /**
     * Indicate that the API key is pro tier.
     */
    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => 'pro',
        ]);
    }

    /**
     * Indicate that the API key is premium tier.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'tier' => 'premium',
        ]);
    }

    /**
     * Indicate that the API key belongs to a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
