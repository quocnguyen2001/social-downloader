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
                    ->label('Client Name')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ]),

                Tables\Columns\TextColumn::make('monthly_requests_count')
                    ->label('This Month Requests')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_revenue')
                    ->label('This Month Revenue')
                    ->money('VND', divideBy: 1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('monthly_usage_percentage')
                    ->label('Monthly Usage %')
                    ->getStateUsing(fn (ApiKey $record) => $record->monthly_usage_percentage.'%')
                    ->badge()
                    ->color(fn (ApiKey $record) => $record->monthly_usage_percentage > 80 ? 'danger' :
                        ($record->monthly_usage_percentage > 60 ? 'warning' : 'success')
                    ),

                Tables\Columns\TextColumn::make('daily_usage_percentage')
                    ->label('Daily Usage %')
                    ->getStateUsing(fn (ApiKey $record) => $record->daily_usage_percentage.'%')
                    ->badge()
                    ->color(fn (ApiKey $record) => $record->daily_usage_percentage > 80 ? 'danger' :
                        ($record->daily_usage_percentage > 60 ? 'warning' : 'success')
                    )
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_per_request')
                    ->label('Price/Request')
                    ->money('VND', divideBy: 1)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_usage')
                    ->label('Total Usage')
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
