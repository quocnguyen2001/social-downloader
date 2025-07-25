<?php

namespace App\Filament\Widgets;

use App\Models\ApiRequest;
use Filament\Widgets\ChartWidget;

class ApiUsageChart extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return trans('messages.widgets.api_usage_chart');
    }

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        // Get data for the last 30 days
        $endDate = now();
        $startDate = now()->subDays(29);

        $dates = [];
        $requestCounts = [];
        $revenueCounts = [];
        $youtubeCounts = [];
        $tiktokCounts = [];
        $instagramCounts = [];
        $facebookCounts = [];

        // Generate data for each day
        for ($date = $startDate->copy(); $date <= $endDate; $date->addDay()) {
            $dateString = $date->format('M j');
            $dates[] = $dateString;

            // Get requests for this day
            $dayRequests = ApiRequest::whereDate('created_at', $date->toDateString());

            $requestCounts[] = $dayRequests->count();
            $revenueCounts[] = round($dayRequests->where('billed', true)->sum('cost'), 2);

            // Platform breakdown
            $youtubeCounts[] = $dayRequests->where('platform', 'youtube')->count();
            $tiktokCounts[] = $dayRequests->where('platform', 'tiktok')->count();
            $instagramCounts[] = $dayRequests->where('platform', 'instagram')->count();
            $facebookCounts[] = $dayRequests->where('platform', 'facebook')->count();
        }

        return [
            'datasets' => [
                [
                    'label' => trans('models.api_request.fields.total_requests'),
                    'data' => $requestCounts,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => trans('models.monthly_billing.fields.total_cost').' (VND)',
                    'data' => $revenueCounts,
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y1',
                ],
                [
                    'label' => trans('enums.platform.youtube'),
                    'data' => $youtubeCounts,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => trans('enums.platform.tiktok'),
                    'data' => $tiktokCounts,
                    'borderColor' => 'rgb(245, 158, 11)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => trans('enums.platform.instagram'),
                    'data' => $instagramCounts,
                    'borderColor' => 'rgb(168, 85, 247)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => trans('enums.platform.facebook'),
                    'data' => $facebookCounts,
                    'borderColor' => 'rgb(99, 102, 241)',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'tension' => 0.4,
                    'yAxisID' => 'y',
                ],
            ],
            'labels' => $dates,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Number of Requests',
                    ],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Revenue (VND)',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
        ];
    }
}
