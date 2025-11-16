<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubscriptionResource\Pages;

use App\Filament\Resources\SubscriptionResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('filament.resources.subscription.sections.subscription_information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label(__('filament.resources.subscription.fields.membership_plan'))
                            ->badge()
                            ->color('info')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('status')
                            ->label(__('filament.resources.subscription.fields.status'))
                            ->badge()
                            ->color(fn ($state) => $state->getColor()),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('filament.common.fields.created_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('filament.common.fields.updated_at'))
                            ->dateTime(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('filament.resources.subscription.sections.pricing_information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')
                            ->label(__('filament.resources.subscription.fields.subtotal'))
                            ->money('VND')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('discount')
                            ->label(__('filament.resources.subscription.fields.discount'))
                            ->money('VND')
                            ->badge()
                            ->color('warning')
                            ->placeholder('No discount'),

                        Infolists\Components\TextEntry::make('total')
                            ->label(__('filament.resources.subscription.fields.total'))
                            ->money('VND')
                            ->badge()
                            ->color('success'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('filament.resources.subscription.sections.transaction_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('transaction.id')
                            ->label(__('filament.resources.transaction.fields.id'))
                            ->placeholder('No transaction associated'),

                        Infolists\Components\TextEntry::make('transaction.status')
                            ->label(__('filament.resources.transaction.fields.status'))
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
                            ->label(__('filament.resources.transaction.fields.amount'))
                            ->money('VND')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.payment_method')
                            ->label(__('filament.resources.transaction.fields.payment_method'))
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.customer_name')
                            ->label(__('filament.resources.transaction.fields.customer_name'))
                            ->placeholder('No transaction'),

                        Infolists\Components\TextEntry::make('transaction.customer_email')
                            ->label(__('filament.resources.transaction.fields.customer_email'))
                            ->placeholder('No transaction'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Infolists\Components\Section::make(__('filament.resources.subscription.sections.membership_details'))
                    ->schema([
                        Infolists\Components\TextEntry::make('membershipPlan.name')
                            ->label(__('filament.resources.membership_plan.fields.name'))
                            ->badge()
                            ->color('primary')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.description')
                            ->label(__('filament.resources.membership_plan.fields.description'))
                            ->placeholder('No description available')
                            ->columnSpanFull(),

                        Infolists\Components\TextEntry::make('membershipPlan.price')
                            ->label(__('filament.resources.membership_plan.fields.price'))
                            ->money('VND')
                            ->badge()
                            ->color('success')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.billing_cycle')
                            ->label(__('filament.resources.membership_plan.fields.billing_cycle'))
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'N/A')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.daily_request_limit')
                            ->label(__('filament.resources.membership_plan.fields.daily_request_limit'))
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'Unlimited' : number_format($state))
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.total_request_download')
                            ->label(__('filament.resources.membership_plan.fields.total_request_download'))
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn (?int $state): string => $state === 0 ? 'Unlimited' : number_format($state))
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.priority_processing')
                            ->label(__('filament.resources.membership_plan.fields.priority_processing'))
                            ->badge()
                            ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Yes' : 'No')
                            ->placeholder('No membership plan'),

                        Infolists\Components\TextEntry::make('membershipPlan.is_active')
                            ->label(__('filament.resources.membership_plan.fields.is_active'))
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
