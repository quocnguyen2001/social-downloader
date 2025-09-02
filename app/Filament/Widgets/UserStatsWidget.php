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
            Stat::make(__('messages.widgets.user_stats.total_users'), number_format($totalUsers))
                ->description(__('messages.widgets.user_stats.total_users_description'))
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make(__('messages.widgets.user_stats.users_with_plans'), number_format($usersWithPlans))
                ->description(__('messages.widgets.user_stats.users_with_plans_description'))
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),

            Stat::make(__('messages.widgets.user_stats.active_members'), number_format($activeMembers))
                ->description(__('messages.widgets.user_stats.active_members_description'))
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('messages.widgets.user_stats.expired_members'), number_format($expiredMembers))
                ->description(__('messages.widgets.user_stats.expired_members_description'))
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
