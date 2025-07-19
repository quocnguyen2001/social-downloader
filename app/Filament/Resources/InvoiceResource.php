<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Models\Invoice;
use App\Models\ApiKey;
use App\Models\MembershipPlan;
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

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Billing & Revenue';

    protected static ?string $navigationLabel = 'Invoices';

    protected static ?string $modelLabel = 'Invoice';

    protected static ?string $pluralModelLabel = 'Invoices';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Invoice Information')
                    ->schema([
                        Forms\Components\Select::make('api_key_id')
                            ->label(trans('messages.table.columns.api_key'))
                            ->options(ApiKey::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->options(MembershipPlan::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

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
                            ->label('YouTube Requests')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('tiktok_requests')
                            ->label('TikTok Requests')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('instagram_requests')
                            ->label('Instagram Requests')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('facebook_requests')
                            ->label('Facebook Requests')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Toggle::make('invoice_sent')
                            ->label('Invoice Sent')
                            ->default(false),

                        Forms\Components\DateTimePicker::make('invoice_sent_at')
                            ->label('Invoice Sent At')
                            ->nullable(),

                        Forms\Components\Toggle::make('paid')
                            ->label('Paid')
                            ->default(false),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('Paid At')
                            ->nullable(),

                        Forms\Components\Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                'bank_transfer' => 'Bank Transfer',
                                'credit_card' => 'Credit Card',
                                'paypal' => 'PayPal',
                                'crypto' => 'Cryptocurrency',
                            ])
                            ->nullable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('apiKey.name')
                    ->label(trans('messages.table.columns.api_key'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label('Membership Plan')
                    ->searchable()
                    ->sortable()
                    ->placeholder('N/A'),

                Tables\Columns\TextColumn::make('billing_month')
                    ->label(trans('messages.labels.billing_month'))
                    ->date('F Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_requests')
                    ->label(trans('messages.labels.total_requests'))
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_cost')
                    ->label(trans('messages.labels.total_cost'))
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('paid')
                    ->label('Payment Status')
                    ->colors([
                        'danger' => false,
                        'success' => true,
                    ])
                    ->icons([
                        'heroicon-o-x-circle' => false,
                        'heroicon-o-check-circle' => true,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Paid' : 'Unpaid'),

                Tables\Columns\BadgeColumn::make('invoice_sent')
                    ->label('Invoice Status')
                    ->colors([
                        'warning' => false,
                        'success' => true,
                    ])
                    ->icons([
                        'heroicon-o-clock' => false,
                        'heroicon-o-paper-airplane' => true,
                    ])
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sent' : 'Not Sent'),

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

                SelectFilter::make('membership_plan_id')
                    ->label('Membership Plan')
                    ->options(MembershipPlan::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('paid')
                    ->options([
                        '1' => 'Paid',
                        '0' => 'Unpaid',
                    ]),

                SelectFilter::make('invoice_sent')
                    ->options([
                        '1' => 'Sent',
                        '0' => 'Not Sent',
                    ]),

                Filter::make('billing_month')
                    ->form([
                        DatePicker::make('billing_from')
                            ->label('Billing From'),
                        DatePicker::make('billing_until')
                            ->label('Billing Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['billing_from'], fn($q) => $q->whereDate('billing_month', '>=', $data['billing_from']))
                            ->when($data['billing_until'], fn($q) => $q->whereDate('billing_month', '<=', $data['billing_until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),

                Tables\Actions\Action::make('send_invoice')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->action(function (Invoice $record) {
                        $record->sendInvoice();
                        Notification::make()
                            ->title('Invoice sent successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invoice $record): bool => !$record->invoice_sent),

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
                    ->action(function (Invoice $record, array $data) {
                        $record->markAsPaid($data['payment_method']);
                        Notification::make()
                            ->title('Invoice marked as paid')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Invoice $record): bool => !$record->paid),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('send_invoices')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('info')
                        ->action(function ($records) {
                            $sentCount = 0;
                            foreach ($records as $record) {
                                if (!$record->invoice_sent) {
                                    $record->sendInvoice();
                                    $sentCount++;
                                }
                            }
                            Notification::make()
                                ->title("Sent {$sentCount} invoices")
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
                                ->title('Invoices marked as paid')
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
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'view' => Pages\ViewInvoice::route('/{record}'),
        ];
    }

    /**
     * Disable edit capabilities for invoices.
     */
    public static function canEdit($record): bool
    {
        return false;
    }
}
