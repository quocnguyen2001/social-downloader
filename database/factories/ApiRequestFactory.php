<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiRequest>
 */
class ApiRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['youtube', 'tiktok', 'instagram', 'facebook'];
        $platform = fake()->randomElement($platforms);
        $statusCode = fake()->randomElement([200, 200, 200, 200, 400, 404, 500]); // Mostly successful
        $cost = fake()->randomFloat(4, 0.0100, 0.1000);

        $videoTitles = [
            'Amazing Cat Video Compilation',
            'How to Cook Perfect Pasta',
            'Top 10 Travel Destinations 2024',
            'Funny Dog Moments',
            'Tech Review: Latest Smartphone',
            'Beautiful Sunset Timelapse',
            'Dance Challenge Compilation',
            'DIY Home Improvement Tips',
        ];

        return [
            'id' => Str::uuid(),
            'api_key_id' => ApiKey::factory(),
            'user_id' => fake()->optional(0.8)->randomElement([
                User::factory(),
                null,
            ]),
            'endpoint' => '/api/v1/download',
            'method' => fake()->randomElement(['GET', 'POST']),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'original_url' => $this->generatePlatformUrl($platform),
            'platform' => $platform,
            'video_title' => fake()->randomElement($videoTitles),
            'requested_quality' => fake()->randomElement(['144p', '360p', '720p', '1080p']),
            'requested_format' => fake()->randomElement(['mp4', 'mp3', 'webm']),
            'status_code' => $statusCode,
            'response_time' => $statusCode === 200 ? fake()->numberBetween(500, 3000) : fake()->numberBetween(100, 1000),
            'file_size' => $statusCode === 200 ? fake()->numberBetween(1048576, 104857600) : null, // 1MB to 100MB
            'download_url' => $statusCode === 200 ? fake()->url() : null,
            'cost' => $cost,
            'billed' => fake()->boolean(80), // 80% chance of being billed
            'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }

    /**
     * Generate a realistic URL for the given platform.
     */
    private function generatePlatformUrl(string $platform): string
    {
        return match ($platform) {
            'youtube' => 'https://www.youtube.com/watch?v='.Str::random(11),
            'tiktok' => 'https://www.tiktok.com/@user/video/'.fake()->numerify('####################'),
            'instagram' => 'https://www.instagram.com/p/'.Str::random(11).'/',
            'facebook' => 'https://www.facebook.com/watch/?v='.fake()->numerify('####################'),
            default => fake()->url(),
        };
    }

    /**
     * Indicate that the request was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => 200,
            'response_time' => fake()->numberBetween(500, 3000),
            'file_size' => fake()->numberBetween(1048576, 104857600),
            'download_url' => fake()->url(),
            'billed' => true,
        ]);
    }

    /**
     * Indicate that the request failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_code' => fake()->randomElement([400, 404, 500]),
            'response_time' => fake()->numberBetween(100, 1000),
            'file_size' => null,
            'download_url' => null,
            'cost' => 0,
            'billed' => false,
        ]);
    }
}
