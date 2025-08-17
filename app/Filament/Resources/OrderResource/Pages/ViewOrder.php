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
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            // Edit action removed as per requirements
        ];
    }
}
