<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiRequestResource\Pages;

use App\Enums\HttpMethod;
use App\Enums\Platform;
use App\Filament\Resources\ApiRequestResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewApiRequest extends ViewRecord
{
    protected static string $resource = ApiRequestResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Information')
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

                        Infolists\Components\TextEntry::make('endpoint')
                            ->label('Endpoint')
                            ->copyable()
                            ->copyMessage('Endpoint copied!')
                            ->icon('heroicon-m-link'),

                        Infolists\Components\TextEntry::make('method')
                            ->label('HTTP Method')
                            ->badge()
                            ->color(fn (HttpMethod $state): string => match ($state->value) {
                                'GET' => 'success',
                                'POST' => 'info',
                                'PUT' => 'warning',
                                'DELETE' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable()
                            ->copyMessage('IP copied!')
                            ->icon('heroicon-m-globe-alt'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Request Time')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Video Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('original_url')
                            ->label('Original URL')
                            ->copyable()
                            ->copyMessage('URL copied!')
                            ->icon('heroicon-m-link')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('platform')
                            ->label('Platform')
                            ->badge()
                            ->color(fn (Platform $state): string => match ($state->value) {
                                'youtube' => 'danger',
                                'instagram' => 'pink',
                                'facebook' => 'blue',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('video_title')
                            ->label('Video Title')
                            ->placeholder('Title not available')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('requested_quality')
                            ->label('Requested Quality')
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('requested_format')
                            ->label('Requested Format')
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Response Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('status_code')
                            ->label('Status Code')
                            ->badge()
                            ->color(fn (int $state): string => match (true) {
                                $state >= 200 && $state < 300 => 'success',
                                $state >= 300 && $state < 400 => 'info',
                                $state >= 400 && $state < 500 => 'warning',
                                $state >= 500 => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('response_time')
                            ->label('Response Time')
                            ->formatStateUsing(fn (?int $state): string => $state ? $state.' ms' : 'Unknown'
                            )
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('file_size')
                            ->label('File Size')
                            ->formatStateUsing(function (?int $state): string {
                                if (! $state) {
                                    return 'Unknown';
                                }

                                $bytes = $state;
                                $units = ['B', 'KB', 'MB', 'GB'];

                                for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                                    $bytes /= 1024;
                                }

                                return round($bytes, 2).' '.$units[$i];
                            })
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('download_url')
                            ->label('Download URL')
                            ->copyable()
                            ->copyMessage('Download URL copied!')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->placeholder('Not available')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Billing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('cost')
                            ->label('Cost')
                            ->money('VND')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\IconEntry::make('billed')
                            ->label('Billed')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('warning'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('User Agent')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_agent')
                            ->label('User Agent')
                            ->placeholder('Not available')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
