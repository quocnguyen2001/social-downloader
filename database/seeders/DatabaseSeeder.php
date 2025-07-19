<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\MonthlyBilling;
use App\Models\DownloadSession;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user for Filament if it doesn't exist
        if (!User::where('email', 'admin@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
            ]);
        }

        // Create 5 API keys with different statuses and configurations
        $apiKeys = collect([
            // Active high-volume client
            ApiKey::factory()->active()->create([
                'name' => 'StreamTech Pro',
                'company_name' => 'StreamTech Solutions Inc.',
                'contact_email' => 'api@streamtech.com',
                'billing_email' => 'billing@streamtech.com',
                'daily_limit' => 5000,
                'monthly_limit' => 150000,
                'daily_usage' => 2500,
                'monthly_usage' => 75000,
                'total_usage' => 500000,
                'price_per_request' => 0.0300,
                'allowed_platforms' => ['youtube', 'tiktok', 'instagram', 'facebook'],
                'allowed_qualities' => ['360p', '720p', '1080p'],
                'allowed_formats' => ['mp4', 'mp3'],
            ]),

            // Active medium client
            ApiKey::factory()->active()->create([
                'name' => 'VideoBot API',
                'company_name' => 'VideoBot Ltd.',
                'contact_email' => 'dev@videobot.io',
                'daily_limit' => 2000,
                'monthly_limit' => 60000,
                'daily_usage' => 800,
                'monthly_usage' => 25000,
                'total_usage' => 150000,
                'price_per_request' => 0.0500,
                'allowed_platforms' => ['youtube', 'tiktok'],
                'allowed_qualities' => ['720p', '1080p'],
                'allowed_formats' => ['mp4'],
            ]),

            // Active small client
            ApiKey::factory()->active()->create([
                'name' => 'Social Media Tools',
                'company_name' => 'SMT Startup',
                'contact_email' => 'hello@smtools.app',
                'daily_limit' => 1000,
                'monthly_limit' => 30000,
                'daily_usage' => 150,
                'monthly_usage' => 4500,
                'total_usage' => 25000,
                'allowed_platforms' => ['instagram', 'tiktok'],
                'allowed_qualities' => ['360p', '720p'],
                'allowed_formats' => ['mp4', 'mp3'],
            ]),

            // Inactive client
            ApiKey::factory()->inactive()->create([
                'name' => 'Legacy App',
                'company_name' => 'Old Tech Corp',
                'contact_email' => 'support@oldtech.com',
                'daily_usage' => 0,
                'monthly_usage' => 0,
                'total_usage' => 50000,
            ]),

            // Suspended client
            ApiKey::factory()->suspended()->create([
                'name' => 'Suspended Client',
                'company_name' => 'Problem Corp',
                'contact_email' => 'admin@problemcorp.com',
                'daily_usage' => 0,
                'monthly_usage' => 0,
                'total_usage' => 10000,
            ]),
        ]);

        // Create API requests for each active API key
        $activeApiKeys = $apiKeys->where('status', 'active');

        foreach ($activeApiKeys as $apiKey) {
            // Create requests for the last 3 months
            for ($month = 2; $month >= 0; $month--) {
                $startDate = now()->subMonths($month)->startOfMonth();
                $endDate = now()->subMonths($month)->endOfMonth();

                // Create 30-100 requests per month for each API key
                $requestCount = fake()->numberBetween(30, 100);

                ApiRequest::factory($requestCount)->create([
                    'api_key_id' => $apiKey->id,
                    'created_at' => fake()->dateTimeBetween($startDate, $endDate),
                ]);
            }
        }

        // Create monthly billing records for the last 3 months
        foreach ($activeApiKeys as $apiKey) {
            for ($month = 2; $month >= 0; $month--) {
                $billingMonth = now()->subMonths($month)->startOfMonth();

                MonthlyBilling::factory()->create([
                    'api_key_id' => $apiKey->id,
                    'billing_month' => $billingMonth->toDateString(),
                ]);
            }
        }

        // Create download sessions for the last week
        foreach ($activeApiKeys as $apiKey) {
            // Create 10-30 download sessions per API key
            $sessionCount = fake()->numberBetween(10, 30);

            DownloadSession::factory($sessionCount)->create([
                'api_key_id' => $apiKey->id,
                'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
            ]);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Created:');
        $this->command->info('- 1 admin user');
        $this->command->info('- 5 API keys (3 active, 1 inactive, 1 suspended)');
        $this->command->info('- ~300+ API requests across 3 months');
        $this->command->info('- 15 monthly billing records');
        $this->command->info('- ~100+ download sessions');
    }
}
