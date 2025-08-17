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
                        Infolists\Components\TextEntry::make('apiKey.name')
                            ->label('API Key')
                            ->badge()
                            ->color('primary'),

                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label('Membership Plan')
                            ->badge()
                            ->color('info')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('billing_month')
                            ->label('Billing Month')
                            ->date('F Y')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('total_requests')
                            ->label('Total Requests')
                            ->numeric()
                            ->badge()
                            ->color('success'),

                        Infolists\Components\TextEntry::make('total_cost')
                            ->label('Total Cost')
                            ->money('VND')
                            ->badge()
                            ->color('warning'),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Payment Status')
                    ->schema([
                        Infolists\Components\IconEntry::make('invoice_sent')
                            ->label('Invoice Sent')
                            ->boolean()
                            ->trueIcon('heroicon-o-paper-airplane')
                            ->falseIcon('heroicon-o-clock')
                            ->trueColor('success')
                            ->falseColor('warning'),

                        Infolists\Components\TextEntry::make('invoice_sent_at')
                            ->label('Invoice Sent At')
                            ->dateTime()
                            ->placeholder('Not sent yet'),

                        Infolists\Components\IconEntry::make('paid')
                            ->label('Payment Status')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Paid At')
                            ->dateTime()
                            ->placeholder('Not paid yet'),

                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Payment Method')
                            ->badge()
                            ->color('info')
                            ->placeholder('No payment method'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Edit action removed as per requirements
        ];
    }
}
