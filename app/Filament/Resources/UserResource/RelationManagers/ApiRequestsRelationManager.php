<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ApiRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'apiRequests';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('endpoint')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('endpoint')
            ->columns([
                Tables\Columns\TextColumn::make('endpoint')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('method')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'GET' => 'success',
                        'POST' => 'info',
                        'PUT' => 'warning',
                        'DELETE' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status_code')
                    ->label('Status')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 200 && $state < 300 => 'success',
                        $state >= 300 && $state < 400 => 'info',
                        $state >= 400 && $state < 500 => 'warning',
                        $state >= 500 => 'danger',
                        default => 'gray',
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

                Tables\Columns\TextColumn::make('video_title')
                    ->label('Video Title')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();

                        return strlen($state) > 30 ? $state : null;
                    }),

                Tables\Columns\TextColumn::make('cost')
                    ->money('USD')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('billed')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('response_time')
                    ->label('Response Time')
                    ->formatStateUsing(fn ($state) => $state ? $state.'ms' : 'N/A')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('file_size')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return 'N/A';
                        }

                        $bytes = $state;
                        $units = ['B', 'KB', 'MB', 'GB'];

                        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                            $bytes /= 1024;
                        }

                        return round($bytes, 2).' '.$units[$i];
                    })
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('method')
                    ->options([
                        'GET' => 'GET',
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                        'DELETE' => 'DELETE',
                    ]),
                Tables\Filters\SelectFilter::make('platform')
                    ->options([
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                    ]),
                Tables\Filters\SelectFilter::make('status_code')
                    ->label('Status Code')
                    ->options([
                        '200' => '200 - Success',
                        '400' => '400 - Bad Request',
                        '401' => '401 - Unauthorized',
                        '403' => '403 - Forbidden',
                        '404' => '404 - Not Found',
                        '429' => '429 - Rate Limited',
                        '500' => '500 - Server Error',
                    ]),
                Tables\Filters\TernaryFilter::make('billed')
                    ->label('Billed'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->headerActions([
                // API requests are typically created via API, not manually
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('mark_billed')
                    ->label('Mark as Billed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn ($record) => $record->update(['billed' => true]))
                    ->visible(fn ($record) => ! $record->billed)
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('mark_billed')
                        ->label('Mark as Billed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['billed' => true]))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
