<?php

namespace App\Console\Commands;

use App\Models\ApiRequest;
use App\Models\DownloadSession;
use App\Models\MembershipPlan;
use App\Models\User;
use Illuminate\Console\Command;

class TestFilamentSetup extends Command
{
    protected $signature = 'filament:test-setup';

    protected $description = 'Test the Filament admin panel setup';

    public function handle()
    {
        $this->info('🧪 Testing Filament Admin Panel Setup...');
        $this->newLine();

        // Test database connections
        $this->info('📊 Database Statistics:');
        $this->line('  Users: '.User::count());
        $this->line('  Membership Plans: '.MembershipPlan::count());
        $this->line('  Download Sessions: '.DownloadSession::count());
        $this->line('  API Requests: '.ApiRequest::count());
        $this->newLine();

        // Test membership plans
        $this->info('💳 Membership Plans:');
        $plans = MembershipPlan::orderBy('sort_order')->get();
        foreach ($plans as $plan) {
            $this->line("  {$plan->name} - {$plan->formatted_price} ({$plan->billing_cycle_label})");
            $this->line("    Daily: {$plan->daily_request_limit}, Total Downloads: ".($plan->total_request_download === 0 ? 'Unlimited' : $plan->total_request_download));
        }
        $this->newLine();

        // Test user with membership
        $this->info('👤 User with Membership Test:');
        $user = User::with('membershipPlan')->first();
        if ($user) {
            $this->line("  User: {$user->name} ({$user->email})");
            if ($user->membershipPlan) {
                $this->line("  Plan: {$user->membershipPlan->name}");
                $this->line('  Active: '.($user->hasMembershipActive() ? 'Yes' : 'No'));
                $this->line('  Daily Remaining: '.$user->getRemainingRequests('daily'));
            } else {
                $this->line('  No membership plan assigned');
            }
        }
        $this->newLine();

        // Test Filament resources
        $this->info('🎛️  Filament Resources:');
        $resources = [
            'UserResource' => \App\Filament\Resources\UserResource::class,
            'MembershipPlanResource' => \App\Filament\Resources\MembershipPlanResource::class,
            'ApiKeyResource' => \App\Filament\Resources\ApiKeyResource::class,
            'DownloadSessionResource' => \App\Filament\Resources\DownloadSessionResource::class,
        ];

        foreach ($resources as $name => $class) {
            if (class_exists($class)) {
                $this->line("  ✅ {$name}");
            } else {
                $this->line("  ❌ {$name} - Class not found");
            }
        }
        $this->newLine();

        // Test widgets
        $this->info('📈 Dashboard Widgets:');
        $widgets = [
            'UserStatsWidget' => \App\Filament\Widgets\UserStatsWidget::class,
            'MembershipPlansChart' => \App\Filament\Widgets\MembershipPlansChart::class,
            'RecentUsersTable' => \App\Filament\Widgets\RecentUsersTable::class,
        ];

        foreach ($widgets as $name => $class) {
            if (class_exists($class)) {
                $this->line("  ✅ {$name}");
            } else {
                $this->line("  ❌ {$name} - Class not found");
            }
        }
        $this->newLine();

        $this->info('✅ Filament setup test completed!');
        $this->info('🌐 Admin panel should be accessible at: /admin');

        return self::SUCCESS;
    }
}
