<?php

namespace App\Filament\Widgets;

use App\Models\MembershipPlan;
use Filament\Widgets\ChartWidget;

class MembershipPlansChart extends ChartWidget
{
    protected static ?string $heading = 'Users by Membership Plan';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $plans = MembershipPlan::withCount('users')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $plans->pluck('users_count')->toArray(),
                    'backgroundColor' => [
                        '#10B981', // green
                        '#3B82F6', // blue
                        '#F59E0B', // amber
                        '#EF4444', // red
                        '#8B5CF6', // purple
                    ],
                ],
            ],
            'labels' => $plans->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
