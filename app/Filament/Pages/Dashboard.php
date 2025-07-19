<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\UserStatsWidget::class,
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\TokenStatsWidget::class,
            \App\Filament\Widgets\MembershipPlansChart::class,
            \App\Filament\Widgets\ApiUsageChart::class,
            \App\Filament\Widgets\TokenUsageChart::class,
            \App\Filament\Widgets\TopClientsWidget::class,
            \App\Filament\Widgets\RecentUsersTable::class,
            \App\Filament\Widgets\RecentTokensWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
