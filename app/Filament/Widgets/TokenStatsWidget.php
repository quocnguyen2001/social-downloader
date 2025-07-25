<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Laravel\Sanctum\PersonalAccessToken;

class TokenStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $totalTokens = PersonalAccessToken::count();
        $activeTokens = PersonalAccessToken::whereNull('expires_at')
            ->orWhere('expires_at', '>', now())
            ->count();
        $expiredTokens = PersonalAccessToken::where('expires_at', '<=', now())->count();
        $usersWithTokens = User::has('tokens')->count();
        $totalUsers = User::count();
        $recentTokens = PersonalAccessToken::where('created_at', '>=', now()->subDays(7))->count();

        return [
            Stat::make('Total API Tokens', $totalTokens)
                ->description('All tokens ever created')
                ->descriptionIcon('heroicon-m-key')
                ->color('primary')
                ->chart($this->getTokenCreationChart()),

            Stat::make('Active Tokens', $activeTokens)
                ->description($expiredTokens > 0 ? "{$expiredTokens} expired" : 'All tokens active')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($expiredTokens > 0 ? 'warning' : 'success'),

            Stat::make('Users with Tokens', $usersWithTokens)
                ->description("Out of {$totalUsers} total users")
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),

            Stat::make('New Tokens (7 days)', $recentTokens)
                ->description('Recently generated')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color($recentTokens > 0 ? 'success' : 'gray'),
        ];
    }

    private function getTokenCreationChart(): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = PersonalAccessToken::whereDate('created_at', $date)->count();
            $data[] = $count;
        }

        return $data;
    }
}
