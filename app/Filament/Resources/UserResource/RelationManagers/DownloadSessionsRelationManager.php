<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DownloadSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'downloadSessions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('original_url')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'youtube' => 'danger',
                        'tiktok' => 'gray',
                        'instagram' => 'warning',
                        'facebook' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('quality')
                    ->badge(),

                Tables\Columns\TextColumn::make('format')
                    ->badge(),

                Tables\Columns\TextColumn::make('file_size')
                    ->formatStateUsing(fn ($record) => $record->formatted_file_size)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('duration')
                    ->formatStateUsing(fn ($record) => $record->formatted_duration)
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->color(fn ($record) => $record?->isExpired() ? 'danger' : 'success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('quality')
                    ->options([
                        '144p' => '144p',
                        '360p' => '360p',
                        '720p' => '720p',
                        '1080p' => '1080p',
                    ]),
            ])
            ->headerActions([
                // Download sessions are typically created via API, not manually
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->download_url)
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->isCompleted() && ! $record->isExpired()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
