<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Custom Dashboard page for the admin panel.
 *
 * Displays key metrics and widgets in a responsive layout.
 */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    /**
     * Get the widgets to display on the dashboard.
     *
     * @return array<string>
     */
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\UserStatsWidget::class,
            \App\Filament\Widgets\StatsOverviewWidget::class,
            \App\Filament\Widgets\TokenStatsWidget::class,
            \App\Filament\Widgets\ApiUsageChart::class,
            \App\Filament\Widgets\TokenUsageChart::class,
            \App\Filament\Widgets\TopClientsWidget::class,
            \App\Filament\Widgets\RecentUsersTable::class,
            \App\Filament\Widgets\RecentTokensWidget::class,
        ];
    }

    /**
     * Get the column configuration for the dashboard layout.
     *
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
