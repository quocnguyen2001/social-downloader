<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Laravel\Sanctum\PersonalAccessToken;

class RecentTokensWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent API Tokens';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PersonalAccessToken::query()
                    ->with('tokenable')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Token Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('tokenable.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->tokenable ? route('filament.admin.resources.users.view', $record->tokenable) : null)
                    ->color('primary'),

                Tables\Columns\TextColumn::make('abilities')
                    ->label('Abilities')
                    ->formatStateUsing(fn (array|string $state) => is_string($state) ? $state : implode(', ', $state))
                    ->limit(20),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->since()
                    ->placeholder('Never')
                    ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_expired')
                    ->label('Status')
                    ->boolean()
                    ->state(fn ($record) => $record->expires_at && $record->expires_at->isPast())
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->action(fn ($record) => $record->delete())
                    ->requiresConfirmation()
                    ->modalHeading('Revoke Token')
                    ->modalDescription('Are you sure you want to revoke this token? This action cannot be undone.')
                    ->visible(fn ($record) => ! ($record->expires_at && $record->expires_at->isPast())),
            ])
            ->emptyStateHeading('No API tokens found')
            ->emptyStateDescription('No API tokens have been created yet.')
            ->defaultSort('created_at', 'desc');
    }
}
