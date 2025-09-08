<?php

namespace App\Filament\Widgets;

use App\Enums\DownloadSessionStatus;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\DownloadSession;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalApiKeys = ApiKey::count();
        $activeApiKeys = ApiKey::active()->count();
        $inactiveApiKeys = ApiKey::inactive()->count();
        $suspendedApiKeys = ApiKey::suspended()->count();

        $todayRequests = ApiRequest::today()->count();
        $todaySuccessfulRequests = ApiRequest::today()->successful()->count();
        $todaySuccessRate = $todayRequests > 0 ? round(($todaySuccessfulRequests / $todayRequests) * 100, 1) : 0;

        $thisMonthRevenue = Transaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->where('status', 'completed')
            ->sum('amount');
        $lastMonthRevenue = Transaction::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->where('status', 'completed')
            ->sum('amount');
        $revenueChange = $lastMonthRevenue > 0 ? round((($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) : 0;

        $downloadSessions = DownloadSession::all();

        $activeDownloadSessions = $downloadSessions->where('status', DownloadSessionStatus::READY_FOR_DOWNLOAD)->count();
        $pendingSessions = $downloadSessions->where('status', DownloadSessionStatus::PENDING)->count();
        $processingSessions = $downloadSessions->where('status', DownloadSessionStatus::FETCHING_METADATA)->count();

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

            Stat::make(trans('messages.widgets.stats_overview.this_months_revenue'), format_currency($thisMonthRevenue, 'VND'))
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
