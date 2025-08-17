<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Enums\OrderStatus;
use App\Models\MembershipPlan;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Billing & Revenue';

    protected static ?string $navigationLabel = 'Orders';

    protected static ?string $modelLabel = 'Order';

    protected static ?string $pluralModelLabel = 'Orders';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Order Information')
                    ->schema([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(MembershipPlan::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(OrderStatus::getOptions())
                            ->required()
                            ->default(OrderStatus::PENDING->value),
                    ])->columns(2),

                Forms\Components\Section::make('Pricing Information')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('Subtotal')
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
                            ->label('Discount')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?float $state, Forms\Get $get) {
                                $subtotal = $get('subtotal') ?? 0;
                                $set('total', $subtotal - $state);
                            }),

                        Forms\Components\TextInput::make('total')
                            ->label('Total')
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
                    ->label('Membership Plan')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('Discount')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => OrderStatus::PENDING->value,
                        'info' => OrderStatus::PROCESSING->value,
                        'success' => OrderStatus::COMPLETED->value,
                    ])
                    ->icons([
                        'heroicon-o-clock' => OrderStatus::PENDING->value,
                        'heroicon-o-arrow-path' => OrderStatus::PROCESSING->value,
                        'heroicon-o-check-circle' => OrderStatus::COMPLETED->value,
                    ])
                    ->formatStateUsing(fn (OrderStatus $state): string => $state->getLabel()),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
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
                    ->options(OrderStatus::getOptions()),

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
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (Order $record) {
                        $record->markAsProcessing();
                        Notification::make()
                            ->title('Order marked as processing')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::PENDING),

                Tables\Actions\Action::make('mark_as_completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Order $record) {
                        $record->markAsCompleted();
                        Notification::make()
                            ->title('Order marked as completed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Order $record): bool => $record->status === OrderStatus::PROCESSING),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('mark_as_processing')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function ($records) {
                            $processedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === OrderStatus::PENDING) {
                                    $record->markAsProcessing();
                                    $processedCount++;
                                }
                            }
                            Notification::make()
                                ->title("Marked {$processedCount} orders as processing")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_as_completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $completedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === OrderStatus::PROCESSING) {
                                    $record->markAsCompleted();
                                    $completedCount++;
                                }
                            }
                            Notification::make()
                                ->title("Marked {$completedCount} orders as completed")
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    /**
     * Disable edit capabilities for orders.
     */
    public static function canEdit($record): bool
    {
        return false;
    }
}
