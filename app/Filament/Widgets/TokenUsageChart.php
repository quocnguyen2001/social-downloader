<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Laravel\Sanctum\PersonalAccessToken;

class TokenUsageChart extends ChartWidget
{
    protected static ?string $heading = 'Token Creation Trend';

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '30s';

    protected function getData(): array
    {
        $data = [];
        $labels = [];

        // Get data for the last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $count = PersonalAccessToken::whereDate('created_at', $date)->count();
            $data[] = $count;
            $labels[] = $date->format('M j');
        }

        return [
            'datasets' => [
                [
                    'label' => 'Tokens Created',
                    'data' => $data,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
