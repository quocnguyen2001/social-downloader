<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MembershipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'membership_plan_id' => fake()->optional(0.7)->randomElement(
                \App\Models\MembershipPlan::query()->pluck('id')->toArray() ?: [null]
            ),
            'membership_started_at' => fake()->optional(0.6)->dateTimeBetween('-1 year', 'now'),
            'membership_expires_at' => fake()->optional(0.6)->dateTimeBetween('now', '+1 year'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has a specific membership plan.
     */
    public function withMembershipPlan(?MembershipPlan $plan = null): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_plan_id' => $plan?->id ?? \App\Models\MembershipPlan::query()->inRandomOrder()->first()?->id,
            'membership_started_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'membership_expires_at' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the user has no membership plan.
     */
    public function withoutMembershipPlan(): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_plan_id' => null,
            'membership_started_at' => null,
            'membership_expires_at' => null,
        ]);
    }

    /**
     * Indicate that the user's membership has expired.
     */
    public function expiredMembership(): static
    {
        return $this->state(fn (array $attributes) => [
            'membership_plan_id' => \App\Models\MembershipPlan::query()->inRandomOrder()->first()?->id,
            'membership_started_at' => fake()->dateTimeBetween('-1 year', '-2 months'),
            'membership_expires_at' => fake()->dateTimeBetween('-2 months', '-1 day'),
        ]);
    }
}
