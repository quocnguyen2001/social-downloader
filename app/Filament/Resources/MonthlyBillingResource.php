<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyBillingResource\Pages;
use App\Models\MonthlyBilling;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class MonthlyBillingResource extends Resource
{
    protected static ?string $model = MonthlyBilling::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Monthly Billing';

    protected static ?string $modelLabel = 'Monthly Billing';

    protected static ?string $pluralModelLabel = 'Monthly Billings';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Billing Information')
                    ->schema([
                        Forms\Components\Select::make('api_key_id')
                            ->label(trans('messages.table.columns.api_key'))
                            ->options(ApiKey::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\DatePicker::make('billing_month')
                            ->required()
                            ->label(trans('messages.labels.billing_month'))
                            ->displayFormat('Y-m-d'),

                        Forms\Components\TextInput::make('total_requests')
                            ->label(trans('messages.labels.total_requests'))
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Forms\Components\TextInput::make('total_cost')
                            ->label(trans('messages.labels.total_cost'))
                            ->numeric()
                            ->step(0.01)
                            ->default(0.00)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Platform Breakdown')
                    ->schema([
                        Forms\Components\TextInput::make('youtube_requests')
                            ->numeric()
                            ->default(0)
                            ->label('YouTube Requests'),

                        Forms\Components\TextInput::make('tiktok_requests')
                            ->numeric()
                            ->default(0)
                            ->label('TikTok Requests'),

                        Forms\Components\TextInput::make('instagram_requests')
                            ->numeric()
                            ->default(0)
                            ->label('Instagram Requests'),

                        Forms\Components\TextInput::make('facebook_requests')
                            ->numeric()
                            ->default(0)
                            ->label('Facebook Requests'),
                    ])->columns(4),

                Forms\Components\Section::make('Payment Status')
                    ->schema([
                        Forms\Components\Toggle::make('invoice_sent')
                            ->label('Invoice Sent'),

                        Forms\Components\DateTimePicker::make('invoice_sent_at')
                            ->label('Invoice Sent At'),

                        Forms\Components\Toggle::make('paid')
                            ->label('Paid'),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Paid At'),

                        Forms\Components\TextInput::make('payment_method')
                            ->label('Payment Method'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('apiKey.name')
                    ->label('API Key')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_billing_month')
                    ->label('Billing Month')
                    ->sortable('billing_month'),

                Tables\Columns\TextColumn::make('total_requests')
                    ->label('Total Requests')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_total_cost')
                    ->label('Total Cost')
                    ->sortable('total_cost'),

                Tables\Columns\TextColumn::make('most_popular_platform')
                    ->label('Top Platform')
                    ->badge()
                    ->colors([
                        'danger' => 'youtube',
                        'warning' => 'tiktok',
                        'success' => 'instagram',
                        'primary' => 'facebook',
                    ]),

                Tables\Columns\IconColumn::make('invoice_sent')
                    ->label('Invoice Sent')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('paid')
                    ->label('Payment Status')
                    ->colors([
                        'success' => true,
                        'danger' => false,
                    ])
                    ->formatStateUsing(fn ($state) => $state ? 'Paid' : 'Unpaid')
                    ->sortable(),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('api_key_id')
                    ->label('API Key')
                    ->options(ApiKey::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('paid')
                    ->label('Payment Status')
                    ->options([
                        '1' => 'Paid',
                        '0' => 'Unpaid',
                    ]),

                SelectFilter::make('invoice_sent')
                    ->label('Invoice Status')
                    ->options([
                        '1' => 'Invoice Sent',
                        '0' => 'Invoice Not Sent',
                    ]),

                Filter::make('billing_month')
                    ->form([
                        DatePicker::make('month_from')
                            ->label('Month from'),
                        DatePicker::make('month_until')
                            ->label('Month until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['month_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('billing_month', '>=', $date),
                            )
                            ->when(
                                $data['month_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('billing_month', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_as_paid')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Select::make('payment_method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer',
                                'credit_card' => 'Credit Card',
                                'paypal' => 'PayPal',
                                'crypto' => 'Cryptocurrency',
                            ])
                            ->required(),
                    ])
                    ->action(function (MonthlyBilling $record, array $data) {
                        $record->markAsPaid($data['payment_method']);
                        Notification::make()
                            ->title('Billing marked as paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (MonthlyBilling $record) => !$record->paid),

                Tables\Actions\Action::make('mark_as_unpaid')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (MonthlyBilling $record) {
                        $record->markAsUnpaid();
                        Notification::make()
                            ->title('Billing marked as unpaid')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (MonthlyBilling $record) => $record->paid),

                Tables\Actions\Action::make('send_invoice')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (MonthlyBilling $record) {
                        $record->sendInvoice();
                        Notification::make()
                            ->title('Invoice sent successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (MonthlyBilling $record) => !$record->invoice_sent),

                Tables\Actions\Action::make('recalculate')
                    ->icon('heroicon-o-calculator')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (MonthlyBilling $record) {
                        $record->calculateTotal();
                        Notification::make()
                            ->title('Billing totals recalculated')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('send_invoices')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->sendInvoice();
                            Notification::make()
                                ->title('Invoices sent successfully')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('mark_as_paid')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->form([
                            Forms\Components\Select::make('payment_method')
                                ->options([
                                    'bank_transfer' => 'Bank Transfer',
                                    'credit_card' => 'Credit Card',
                                    'paypal' => 'PayPal',
                                    'crypto' => 'Cryptocurrency',
                                ])
                                ->required(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each->markAsPaid($data['payment_method']);
                            Notification::make()
                                ->title('Billings marked as paid')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('billing_month', 'desc');
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
            'index' => Pages\ListMonthlyBillings::route('/'),
            'create' => Pages\CreateMonthlyBilling::route('/create'),
            'edit' => Pages\EditMonthlyBilling::route('/{record}/edit'),
        ];
    }
}
