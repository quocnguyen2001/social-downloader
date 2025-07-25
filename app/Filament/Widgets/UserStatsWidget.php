<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalUsers = User::count();
        $usersWithPlans = User::whereNotNull('membership_plan_id')->count();
        $activeMembers = User::whereNotNull('membership_plan_id')
            ->where(function ($query) {
                $query->whereNull('membership_expires_at')
                    ->orWhere('membership_expires_at', '>', now());
            })
            ->count();
        $expiredMembers = User::whereNotNull('membership_expires_at')
            ->where('membership_expires_at', '<', now())
            ->count();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Users with Plans', number_format($usersWithPlans))
                ->description('Users assigned to membership plans')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make('Active Members', number_format($activeMembers))
                ->description('Users with active memberships')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Expired Members', number_format($expiredMembers))
                ->description('Users with expired memberships')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
