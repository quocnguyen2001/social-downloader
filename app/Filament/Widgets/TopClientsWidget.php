<?php

namespace App\Filament\Widgets;

use App\Models\ApiKey;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopClientsWidget extends BaseWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return trans('messages.widgets.top_clients');
    }

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ApiKey::query()
                    ->withCount([
                        'apiRequests as monthly_requests_count' => function (Builder $query) {
                            $query->thisMonth();
                        },
                    ])
                    ->withSum([
                        'apiRequests as monthly_revenue' => function (Builder $query) {
                            $query->thisMonth()->where('billed', true);
                        },
                    ], 'cost')
                    ->having('monthly_requests_count', '>', 0)
                    ->orderByDesc('monthly_requests_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('messages.table.columns.client_name'))
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('company_name')
                    ->label(__('messages.table.columns.company'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('messages.table.columns.status'))
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ]),

                Tables\Columns\TextColumn::make('monthly_requests_count')
                    ->label(__('messages.table.columns.monthly_requests'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_revenue')
                    ->label(__('messages.table.columns.monthly_revenue'))
                    ->money('VND', divideBy: 1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_usage_percentage')
                    ->label(__('messages.table.columns.monthly_usage_percent'))
                    ->getStateUsing(fn (ApiKey $record) => $record->monthly_usage_percentage.'%')
                    ->badge()
                    ->color(fn (ApiKey $record) => $record->monthly_usage_percentage > 80 ? 'danger' :
                        ($record->monthly_usage_percentage > 60 ? 'warning' : 'success')
                    ),

                Tables\Columns\TextColumn::make('daily_usage_percentage')
                    ->label(__('messages.table.columns.daily_usage_percent'))
                    ->getStateUsing(fn (ApiKey $record) => $record->daily_usage_percentage.'%')
                    ->badge()
                    ->color(fn (ApiKey $record) => $record->daily_usage_percentage > 80 ? 'danger' :
                        ($record->daily_usage_percentage > 60 ? 'warning' : 'success')
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_per_request')
                    ->label(__('messages.table.columns.price_request'))
                    ->money('VND', divideBy: 1)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_usage')
                    ->label(__('messages.table.columns.total_usage'))
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('monthly_requests_count', 'desc')
            ->paginated(false);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10];
    }

    public static function canView(): bool
    {
        return true;
    }
}
