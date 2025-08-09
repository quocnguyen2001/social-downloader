<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use Illuminate\Database\Seeder;

class MembershipPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Basic plan with limited features for personal use.',
                'price' => 0.00,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'daily_request_limit' => 10,
                'total_request_download' => 200,
                'allowed_platforms' => ['youtube', 'tiktok'],
                'allowed_qualities' => ['360p', '720p'],
                'allowed_formats' => ['mp4', 'mp3'],
                'priority_processing' => false,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Perfect for regular users who need more downloads.',
                'price' => 9.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'daily_request_limit' => 50,
                'total_request_download' => 1000,
                'allowed_platforms' => ['youtube', 'tiktok', 'instagram'],
                'allowed_qualities' => ['360p', '720p', '1080p'],
                'allowed_formats' => ['mp4', 'webm', 'mp3'],
                'priority_processing' => false,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Advanced plan for power users and content creators.',
                'price' => 19.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'daily_request_limit' => 200,
                'total_request_download' => 5000,
                'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
                'allowed_qualities' => ['144p', '360p', '720p', '1080p'],
                'allowed_formats' => ['mp4', 'webm', 'mp3'],
                'priority_processing' => true,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Ultimate plan with unlimited downloads and premium features.',
                'price' => 49.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'daily_request_limit' => 0, // Unlimited
                'total_request_download' => 0, // Unlimited
                'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
                'allowed_qualities' => ['144p', '360p', '720p', '1080p'],
                'allowed_formats' => ['mp4', 'webm', 'mp3'],
                'priority_processing' => true,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom enterprise solution with dedicated support.',
                'price' => 199.99,
                'currency' => 'USD',
                'billing_cycle' => 'monthly',
                'daily_request_limit' => 0, // Unlimited
                'total_request_download' => 0, // Unlimited
                'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
                'allowed_qualities' => ['144p', '360p', '720p', '1080p'],
                'allowed_formats' => ['mp4', 'webm', 'mp3'],
                'priority_processing' => true,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $planData) {
            MembershipPlan::query()->updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }

        $this->command->info('Membership plans seeded successfully!');
    }
}
