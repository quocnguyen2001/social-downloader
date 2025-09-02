<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\ApiUsageChart;
use App\Filament\Widgets\StatsOverviewWidget;
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
            StatsOverviewWidget::class,
            UserStatsWidget::class,
            ApiUsageChart::class,
            TopClientsWidget::class,
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
            'lg' => 2,
            'xl' => 2,
            '2xl' => 2,
        ];
    }
}
