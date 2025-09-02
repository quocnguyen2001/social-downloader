<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label('Membership Plan')
                            ->badge()
                            ->color('info')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn ($state) => $state->getColor()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pricing Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('VND')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('discount')
                            ->label('Discount')
                            ->money('VND')
                            ->badge()
                            ->color('warning')
                            ->placeholder('No discount'),

                        Infolists\Components\TextEntry::make('total')
                            ->label('Total')
                            ->money('VND')
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Transaction Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('transaction.id')
                            ->label('Transaction ID')
                            ->placeholder('No transaction associated'),

                        Infolists\Components\TextEntry::make('transaction.status')
                            ->label('Transaction Status')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'pending' => 'warning',
                                'completed' => 'success',
                                'failed' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.amount')
                            ->label('Transaction Amount')
                            ->money('VND')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.payment_method')
                            ->label('Payment Method')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.customer_name')
                            ->label('Customer Name')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.customer_email')
                            ->label('Customer Email')
                            ->placeholder('No transaction'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make('Membership Plan Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label('Plan Name')
                            ->badge()
                            ->color('primary')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.description')
                            ->label('Description')
                            ->placeholder('No description available')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('membershipPlan.price')
                            ->label('Plan Price')
                            ->money('VND')
                            ->badge()
                            ->color('success')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.billing_cycle')
                            ->label('Billing Cycle')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.daily_request_limit')
                            ->label('Daily Request Limit')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'Unlimited' : number_format($state))
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.total_request_download')
                            ->label('Total Request Limit')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'Unlimited' : number_format($state))
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.priority_processing')
                            ->label('Priority Processing')
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Yes' : 'No')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.is_active')
                            ->label('Plan Status')
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Active' : 'Inactive')
                            ->placeholder('No membership plan'),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Edit action removed as per requirements
        ];
    }
}
