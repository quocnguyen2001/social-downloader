<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ApiUsageChart;
use App\Filament\Widgets\RecentTokensWidget;
use App\Filament\Widgets\RecentUsersTable;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\TokenStatsWidget;
use App\Filament\Widgets\TokenUsageChart;
use App\Filament\Widgets\TopClientsWidget;
use App\Filament\Widgets\UserStatsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    /**
     * @return array<string>
     */
    public function getWidgets(): array
    {
        return [
            UserStatsWidget::class,
            StatsOverviewWidget::class,
            TokenStatsWidget::class,
            ApiUsageChart::class,
            TokenUsageChart::class,
            TopClientsWidget::class,
            RecentUsersTable::class,
            RecentTokensWidget::class,
        ];
    }

    /**
     * @return int|string|array<string, int>
     */
    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 3,
            '2xl' => 3,
        ];
    }
}
