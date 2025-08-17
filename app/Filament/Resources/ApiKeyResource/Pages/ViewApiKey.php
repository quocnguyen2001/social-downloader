<?php

declare(strict_types=1);

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Enums\ApiKeyStatus;
use App\Filament\Resources\ApiKeyResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewApiKey extends ViewRecord
{
    protected static string $resource = ApiKeyResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('API Key Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('key_prefix')
                            ->label('Key Prefix')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (ApiKeyStatus $state): string => match ($state->value) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pricing & Limits')
                    ->schema([
                        Infolists\Components\TextEntry::make('price_per_request')
                            ->label('Price per Request')
                            ->money('VND')
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('daily_limit')
                            ->label('Daily Limit')
                            ->numeric()
                            ->badge()
                            ->color('info'),

                        Infolists\Components\TextEntry::make('monthly_limit')
                            ->label('Monthly Limit')
                            ->numeric()
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Usage Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('daily_usage')
                            ->label('Daily Usage')
                            ->numeric()
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('monthly_usage')
                            ->label('Monthly Usage')
                            ->numeric()
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('total_usage')
                            ->label('Total Usage')
                            ->numeric()
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('last_reset_daily')
                            ->label('Last Daily Reset')
                            ->date(),

                        Infolists\Components\TextEntry::make('last_reset_monthly')
                            ->label('Last Monthly Reset')
                            ->date(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Contact Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('contact_email')
                            ->label('Contact Email')
                            ->copyable()
                            ->copyMessage('Email copied!')
                            ->icon('heroicon-m-envelope'),

                        Infolists\Components\TextEntry::make('billing_email')
                            ->label('Billing Email')
                            ->copyable()
                            ->copyMessage('Email copied!')
                            ->icon('heroicon-m-envelope')
                            ->placeholder('Same as contact email'),

                        Infolists\Components\TextEntry::make('company_name')
                            ->label('Company Name')
                            ->placeholder('No company specified'),

                        Infolists\Components\TextEntry::make('webhook_url')
                            ->label('Webhook URL')
                            ->copyable()
                            ->copyMessage('URL copied!')
                            ->icon('heroicon-m-link')
                            ->placeholder('No webhook configured'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Permissions')
                    ->schema([
                        Infolists\Components\TextEntry::make('allowed_platforms')
                            ->label('Allowed Platforms')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All platforms allowed'),

                        Infolists\Components\TextEntry::make('allowed_qualities')
                            ->label('Allowed Qualities')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All qualities allowed'),

                        Infolists\Components\TextEntry::make('allowed_formats')
                            ->label('Allowed Formats')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All formats allowed'),
                    ])
                    ->columns(1),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
