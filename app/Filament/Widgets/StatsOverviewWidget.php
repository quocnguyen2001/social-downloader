<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\DownloadSession;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        // Get API Keys stats
        $totalApiKeys = ApiKey::count();
        $activeApiKeys = ApiKey::active()->count();
        $inactiveApiKeys = ApiKey::inactive()->count();
        $suspendedApiKeys = ApiKey::suspended()->count();

        // Get today's requests
        $todayRequests = ApiRequest::today()->count();
        $todaySuccessfulRequests = ApiRequest::today()->successful()->count();
        $todaySuccessRate = $todayRequests > 0 ? round(($todaySuccessfulRequests / $todayRequests) * 100, 1) : 0;

        // Get this month's revenue
        $thisMonthRevenue = ApiRequest::thisMonth()->where('billed', true)->sum('cost');
        $lastMonthRevenue = ApiRequest::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->where('billed', true)
            ->sum('cost');
        $revenueChange = $lastMonthRevenue > 0 ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

        // Get active download sessions
        $activeDownloadSessions = DownloadSession::active()->count();
        $pendingSessions = DownloadSession::pending()->count();
        $processingSessions = DownloadSession::processing()->count();
        $completedToday = DownloadSession::completed()->whereDate('created_at', today())->count();

        return [
            Stat::make(trans('messages.widgets.stats_overview.total_api_keys'), $totalApiKeys)
                ->description("{$activeApiKeys} ".trans('enums.api_key_status.active').", {$inactiveApiKeys} ".trans('enums.api_key_status.inactive').", {$suspendedApiKeys} ".trans('enums.api_key_status.suspended'))
                ->descriptionIcon('heroicon-m-key')
                ->color('primary')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make(trans('messages.widgets.stats_overview.todays_requests'), number_format($todayRequests))
                ->description(trans('messages.info.success_rate', ['rate' => $todaySuccessRate]))
                ->descriptionIcon($todaySuccessRate >= 95 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($todaySuccessRate >= 95 ? 'success' : ($todaySuccessRate >= 80 ? 'warning' : 'danger'))
                ->chart([12, 15, 8, 22, 18, 25, $todayRequests]),

            Stat::make(trans('messages.widgets.stats_overview.this_months_revenue'), number_format($thisMonthRevenue, 2).' VND')
                ->description(trans('messages.info.revenue_change', ['change' => ($revenueChange >= 0 ? '+' : '').$revenueChange]))
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueChange >= 0 ? 'success' : 'danger')
                ->chart([1200, 1800, 2100, 1500, 2400, 1900, $thisMonthRevenue]),

            Stat::make(trans('messages.widgets.stats_overview.active_download_sessions'), number_format($activeDownloadSessions))
                ->description(trans('messages.info.pending_processing', ['pending' => $pendingSessions, 'processing' => $processingSessions]))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('info')
                ->chart([5, 8, 12, 7, 15, 10, $activeDownloadSessions]),
        ];
    }

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';
}
