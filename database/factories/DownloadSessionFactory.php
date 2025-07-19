<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DownloadSession>
 */
class DownloadSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platforms = ['youtube', 'facebook', 'instagram', 'tiktok'];
        $platform = fake()->randomElement($platforms);
        $status = fake()->randomElement(['pending', 'processing', 'completed', 'failed', 'expired']);

        $videoTitles = [
            'Amazing Cat Video Compilation',
            'How to Cook Perfect Pasta',
            'Top 10 Travel Destinations 2024',
            'Funny Dog Moments',
            'Tech Review: Latest Smartphone',
            'Beautiful Sunset Timelapse',
            'Dance Challenge Compilation',
            'DIY Home Improvement Tips',
            'Music Video - Latest Hit',
            'Tutorial: Learn Something New'
        ];

        return [
            'id' => Str::uuid(),
            'api_key_id' => ApiKey::factory(),
            'user_id' => fake()->optional(0.8)->randomElement([
                User::factory(),
                null,
            ]),
            'original_url' => $this->generatePlatformUrl($platform),
            'platform' => $platform,
            'video_id' => $this->generateVideoId($platform),
            'title' => fake()->randomElement($videoTitles),
            'thumbnail_url' => fake()->imageUrl(640, 480, 'video'),
            'duration' => fake()->numberBetween(30, 3600), // 30 seconds to 1 hour
            'quality' => fake()->randomElement(['144p', '360p', '720p', '1080p']),
            'format' => fake()->randomElement(['mp4', 'mp3', 'webm']),
            'file_size' => $status === 'completed' ? fake()->numberBetween(1048576, 104857600) : null,
            'download_url' => $status === 'completed' ? fake()->url() : null,
            'status' => $status,
            'error_message' => $status === 'failed' ? fake()->sentence() : null,
            'expires_at' => in_array($status, ['completed', 'failed']) ? fake()->dateTimeBetween('now', '+24 hours') : null,
            'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    /**
     * Generate a realistic URL for the given platform.
     */
    private function generatePlatformUrl(string $platform): string
    {
        return match ($platform) {
            'youtube' => 'https://www.youtube.com/watch?v=' . Str::random(11),
            'tiktok' => 'https://www.tiktok.com/@user/video/' . fake()->numerify('####################'),
            'instagram' => 'https://www.instagram.com/p/' . Str::random(11) . '/',
            'facebook' => 'https://www.facebook.com/watch/?v=' . fake()->numerify('####################'),
            default => fake()->url(),
        };
    }

    /**
     * Generate a realistic video ID for the given platform.
     */
    private function generateVideoId(string $platform): string
    {
        return match ($platform) {
            'youtube' => Str::random(11),
            'tiktok' => fake()->numerify('####################'),
            'instagram' => Str::random(11),
            'facebook' => fake()->numerify('####################'),
            default => Str::random(10),
        };
    }

    /**
     * Indicate that the session is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'file_size' => fake()->numberBetween(1048576, 104857600),
            'download_url' => fake()->url(),
            'error_message' => null,
            'expires_at' => fake()->dateTimeBetween('now', '+24 hours'),
        ]);
    }

    /**
     * Indicate that the session failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'file_size' => null,
            'download_url' => null,
            'error_message' => fake()->sentence(),
            'expires_at' => fake()->dateTimeBetween('now', '+24 hours'),
        ]);
    }

    /**
     * Indicate that the session is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'file_size' => null,
            'download_url' => null,
            'error_message' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the session is processing.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'file_size' => null,
            'download_url' => null,
            'error_message' => null,
            'expires_at' => null,
        ]);
    }
}
