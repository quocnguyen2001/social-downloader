<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->icon('heroicon-m-user'),

                        Infolists\Components\TextEntry::make('email')
                            ->label('Email')
                            ->copyable()
                            ->copyMessage('Email copied!')
                            ->icon('heroicon-m-envelope'),

                        Infolists\Components\IconEntry::make('email_verified_at')
                            ->label('Email Verified')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Member Since')
                            ->dateTime()
                            ->icon('heroicon-m-calendar'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Membership Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label('Membership Plan')
                            ->badge()
                            ->color('primary')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membership_started_at')
                            ->label('Membership Started')
                            ->dateTime()
                            ->placeholder('Not started'),

                        Infolists\Components\TextEntry::make('membership_expires_at')
                            ->label('Membership Expires')
                            ->dateTime()
                            ->placeholder('No expiration')
                            ->color(fn ($state) => $state && $state->isPast() ? 'danger' : 'success'),

                        Infolists\Components\TextEntry::make('membershipPlan.formatted_price')
                            ->label('Plan Price')
                            ->badge()
                            ->color('success')
                            ->placeholder('Free'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('downloadSessions_count')
                            ->label('Total Download Sessions')
                            ->numeric()
                            ->badge()
                            ->color('info')
                            ->state(fn ($record) => $record->downloadSessions()->count()),

                        Infolists\Components\TextEntry::make('apiRequests_count')
                            ->label('Total API Requests')
                            ->numeric()
                            ->badge()
                            ->color('warning')
                            ->state(fn ($record) => $record->apiRequests()->count()),

                        Infolists\Components\TextEntry::make('transactions_count')
                            ->label('Total Transactions')
                            ->numeric()
                            ->badge()
                            ->color('success')
                            ->state(fn ($record) => $record->transactions()->count()),

                        Infolists\Components\TextEntry::make('recent_activity')
                            ->label('Last Activity')
                            ->state(function ($record) {
                                $lastDownload = $record->downloadSessions()->latest()->first();
                                $lastRequest = $record->apiRequests()->latest()->first();

                                $latest = collect([$lastDownload, $lastRequest])
                                    ->filter()
                                    ->sortByDesc('created_at')
                                    ->first();

                                return $latest ? $latest->created_at->diffForHumans() : 'No recent activity';
                            })
                            ->badge()
                            ->color('gray'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
