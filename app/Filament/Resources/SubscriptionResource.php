<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Enums\SubscriptionStatus;
use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.billing_revenue');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.subscriptions');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.subscription.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.subscription.plural_label');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.subscription.sections.subscription_information'))
                    ->schema([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label(__('filament.resources.subscription.fields.membership_plan'))
                            ->options(MembershipPlan::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label(__('filament.resources.subscription.fields.status'))
                            ->options(SubscriptionStatus::getOptions())
                            ->required()
                            ->default(SubscriptionStatus::PENDING->value),
                    ])->columns(2),

                Forms\Components\Section::make(__('filament.resources.subscription.sections.pricing_information'))
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label(__('filament.resources.subscription.fields.subtotal'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00)
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?float $state, Forms\Get $get) {
                                $discount = $get('discount') ?? 0;
                                $set('total', $state - $discount);
                            }),

                        Forms\Components\TextInput::make('discount')
                            ->label(__('filament.resources.subscription.fields.discount'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?float $state, Forms\Get $get) {
                                $subtotal = $get('subtotal') ?? 0;
                                $set('total', $subtotal - $state);
                            }),

                        Forms\Components\TextInput::make('total')
                            ->label(__('filament.resources.subscription.fields.total'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00)
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label(__('filament.resources.subscription.columns.membership_plan'))
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label(__('filament.resources.subscription.columns.subtotal'))
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label(__('filament.resources.subscription.columns.discount'))
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label(__('filament.resources.subscription.columns.total'))
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('filament.resources.subscription.columns.status'))
                    ->colors([
                        'warning' => SubscriptionStatus::PENDING->value,
                        'info' => SubscriptionStatus::PROCESSING->value,
                        'success' => SubscriptionStatus::COMPLETED->value,
                    ])
                    ->icons([
                        'heroicon-o-clock' => SubscriptionStatus::PENDING->value,
                        'heroicon-o-arrow-path' => SubscriptionStatus::PROCESSING->value,
                        'heroicon-o-check-circle' => SubscriptionStatus::COMPLETED->value,
                    ])
                    ->formatStateUsing(fn (SubscriptionStatus $state): string => $state->getLabel()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.common.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.common.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('membership_plan_id')
                    ->label('Membership Plan')
                    ->options(MembershipPlan::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(SubscriptionStatus::getOptions()),

                Filter::make('total_range')
                    ->form([
                        Forms\Components\TextInput::make('total_from')
                            ->label('Total From')
                            ->numeric(),
                        Forms\Components\TextInput::make('total_to')
                            ->label('Total To')
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['total_from'], fn ($q) => $q->where('total', '>=', $data['total_from']))
                            ->when($data['total_to'], fn ($q) => $q->where('total', '<=', $data['total_to']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('mark_as_processing')
                    ->label(__('filament.resources.subscription.actions.mark_as_processing'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (Subscription $record) {
                        $record->markAsProcessing();
                        Notification::make()
                            ->title(__('filament.resources.subscription.messages.subscription_marked_processing'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::PENDING),

                Tables\Actions\Action::make('mark_as_completed')
                    ->label(__('filament.resources.subscription.actions.mark_as_completed'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Subscription $record) {
                        $record->markAsCompleted();
                        Notification::make()
                            ->title(__('filament.resources.subscription.messages.subscription_marked_completed'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Subscription $record): bool => $record->status === SubscriptionStatus::PROCESSING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_as_processing')
                        ->label(__('filament.resources.subscription.actions.mark_as_processing'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function ($records) {
                            $processedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === SubscriptionStatus::PENDING) {
                                    $record->markAsProcessing();
                                    $processedCount++;
                                }
                            }
                            Notification::make()
                                ->title(__('filament.resources.subscription.messages.marked_subscriptions_processing', ['count' => $processedCount]))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_as_completed')
                        ->label(__('filament.resources.subscription.actions.mark_as_completed'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $completedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === SubscriptionStatus::PROCESSING) {
                                    $record->markAsCompleted();
                                    $completedCount++;
                                }
                            }
                            Notification::make()
                                ->title(__('filament.resources.subscription.messages.marked_subscriptions_completed', ['count' => $completedCount]))
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'create' => Pages\CreateSubscription::route('/create'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }

    /**
     * Disable edit capabilities for subscriptions.
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Optimize query performance with eager loading.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transaction', 'membershipPlan']);
    }
}
