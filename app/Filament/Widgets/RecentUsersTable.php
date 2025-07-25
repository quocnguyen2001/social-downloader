<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentUsersTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Users';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->with('membershipPlan')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color(fn ($record) => match ($record->membershipPlan?->slug) {
                        'free' => 'gray',
                        'basic' => 'info',
                        'pro' => 'success',
                        'premium' => 'warning',
                        'enterprise' => 'danger',
                        default => 'gray',
                    })
                    ->default('No Plan'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
                    ->icon('heroicon-m-eye'),
            ]);
    }
}
