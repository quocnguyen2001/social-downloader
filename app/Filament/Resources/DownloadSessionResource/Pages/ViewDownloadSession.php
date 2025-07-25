<?php

declare(strict_types=1);

namespace App\Filament\Resources\DownloadSessionResource\Pages;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Filament\Resources\DownloadSessionResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewDownloadSession extends ViewRecord
{
    protected static string $resource = DownloadSessionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Session Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('apiKey.name')
                            ->label('API Key')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('User')
                            ->badge()
                            ->color('info')
                            ->placeholder('No user associated'),

                        Infolists\Components\TextEntry::make('platform')
                            ->label('Platform')
                            ->badge()
                            ->color(fn (Platform $state): string => match ($state->value) {
                                'youtube' => 'danger',
                                'instagram' => 'pink',
                                'facebook' => 'blue',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (DownloadSessionStatus $state): string => match ($state->value) {
                                'completed' => 'success',
                                'processing' => 'warning',
                                'pending' => 'info',
                                'failed' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('expires_at')
                            ->label('Expires At')
                            ->dateTime()
                            ->placeholder('No expiration'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Video Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('original_url')
                            ->label('Original URL')
                            ->copyable()
                            ->copyMessage('URL copied!')
                            ->icon('heroicon-m-link')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('video_id')
                            ->label('Video ID')
                            ->copyable()
                            ->copyMessage('Video ID copied!')
                            ->placeholder('Not extracted'),

                        Infolists\Components\TextEntry::make('title')
                            ->label('Title')
                            ->placeholder('Title not available')
                            ->columnSpanFull(),

                        Infolists\Components\ImageEntry::make('thumbnail_url')
                            ->label('Thumbnail')
                            ->height(200)
                            ->placeholder('No thumbnail available'),

                        Infolists\Components\TextEntry::make('duration')
                            ->label('Duration')
                            ->formatStateUsing(fn (?int $state): string => $state ? gmdate('H:i:s', $state) : 'Unknown'
                            )
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Error Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('error_message')
                            ->label('Error Message')
                            ->color('danger')
                            ->placeholder('No errors')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => ! empty($record->error_message)),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Edit action removed as per requirements
        ];
    }
}
