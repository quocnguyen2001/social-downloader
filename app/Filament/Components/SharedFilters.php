<?php

namespace App\Filament\Components;

use App\Enums\ApiKeyStatus;
use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Models\ApiKey;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class SharedFilters
{
    /**
     * Common API key filter.
     */
    public static function apiKeyFilter(): SelectFilter
    {
        return SelectFilter::make('api_key_id')
            ->label(trans('messages.table.columns.api_key'))
            ->options(ApiKey::pluck('name', 'id'))
            ->searchable();
    }

    /**
     * Common platform filter.
     */
    public static function platformFilter(): SelectFilter
    {
        return SelectFilter::make('platform')
            ->label(trans('messages.table.filters.platform'))
            ->options(Platform::getOptions());
    }

    /**
     * Common API key status filter.
     */
    public static function apiKeyStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(trans('messages.table.filters.status'))
            ->options(ApiKeyStatus::getOptions());
    }

    /**
     * Common download session status filter.
     */
    public static function downloadSessionStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->options(DownloadSessionStatus::getOptions());
    }

    /**
     * Common date range filter.
     */
    public static function dateRangeFilter(): Filter
    {
        return Filter::make('created_at')
            ->form(SharedFormComponents::dateRangeFilter())
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['created_from'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                    )
                    ->when(
                        $data['created_until'],
                        fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                    );
            });
    }

    /**
     * Common status code filter for API requests.
     */
    public static function statusCodeFilter(): SelectFilter
    {
        return SelectFilter::make('status_code')
            ->label(trans('messages.table.filters.status_code'))
            ->options([
                '200' => trans('messages.filters.success'),
                '400' => trans('messages.filters.bad_request'),
                '404' => trans('messages.filters.not_found'),
                '500' => trans('messages.filters.server_error'),
            ]);
    }

    /**
     * Common billed filter for API requests.
     */
    public static function billedFilter(): SelectFilter
    {
        return SelectFilter::make('billed')
            ->label(trans('messages.table.filters.billed'))
            ->options([
                '1' => trans('messages.filters.billed'),
                '0' => trans('messages.filters.not_billed'),
            ]);
    }
}
