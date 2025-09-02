<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

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
        return __('filament.navigation.labels.transactions');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.transaction.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.transaction.plural_label');
    }

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        // Not used since we only have read-only views
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label(__('filament.resources.transaction.columns.customer_name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label(__('filament.resources.transaction.columns.customer_email'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('filament.resources.transaction.columns.amount'))
                    ->money('VND')
                    ->sortable(),

                Tables\Columns\TextColumn::make('currency')
                    ->label(__('filament.resources.transaction.columns.currency'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('filament.resources.transaction.columns.status'))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->icons([
                        'heroicon-o-clock' => 'pending',
                        'heroicon-o-check-circle' => 'completed',
                        'heroicon-o-x-circle' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => __('filament.resources.transaction.status_options.'.$state)),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('filament.resources.transaction.columns.payment_method'))
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('charge_id')
                    ->label(__('filament.resources.transaction.columns.charge_id'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('order_id')
                    ->label(__('filament.resources.transaction.columns.order_id'))
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.transaction.columns.created_at'))
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.transaction.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('filament.resources.transaction.filters.status'))
                    ->options([
                        'pending' => __('filament.resources.transaction.status_options.pending'),
                        'completed' => __('filament.resources.transaction.status_options.completed'),
                        'failed' => __('filament.resources.transaction.status_options.failed'),
                    ]),

                SelectFilter::make('payment_method')
                    ->label(__('filament.resources.transaction.filters.payment_method'))
                    ->options(function () {
                        return Transaction::query()
                            ->distinct()
                            ->pluck('payment_method', 'payment_method')
                            ->filter()
                            ->toArray();
                    }),

                SelectFilter::make('currency')
                    ->label(__('filament.resources.transaction.filters.currency'))
                    ->options(function () {
                        return Transaction::query()
                            ->distinct()
                            ->pluck('currency', 'currency')
                            ->filter()
                            ->toArray();
                    }),

                Filter::make('amount_range')
                    ->label(__('filament.resources.transaction.filters.amount_range'))
                    ->form([
                        Forms\Components\TextInput::make('amount_from')
                            ->label(__('filament.resources.transaction.filters.amount_from'))
                            ->numeric(),
                        Forms\Components\TextInput::make('amount_to')
                            ->label(__('filament.resources.transaction.filters.amount_to'))
                            ->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['amount_from'], fn ($q) => $q->where('amount', '>=', $data['amount_from']))
                            ->when($data['amount_to'], fn ($q) => $q->where('amount', '<=', $data['amount_to']));
                    }),

                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label(__('filament.resources.transaction.filters.created_from')),
                        Forms\Components\DatePicker::make('created_until')
                            ->label(__('filament.resources.transaction.filters.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn ($q) => $q->whereDate('created_at', '>=', $data['created_from']))
                            ->when($data['created_until'], fn ($q) => $q->whereDate('created_at', '<=', $data['created_until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('mark_as_completed')
                    ->label('Mark as Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Mark Transaction as Completed')
                    ->modalDescription('Are you sure you want to mark this transaction as completed? This action cannot be undone.')
                    ->action(function (Transaction $record) {
                        $record->markAsCompleted();
                        Notification::make()
                            ->title('Transaction marked as completed successfully')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Transaction $record): bool => $record->status !== 'completed'),
            ])
            ->bulkActions([
                // No bulk actions for read-only resource
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make(__('filament.resources.transaction.sections.transaction_details'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('id')
                                    ->label(__('filament.resources.transaction.fields.transaction_id')),
                                Infolists\Components\TextEntry::make('status')
                                    ->label(__('filament.resources.transaction.fields.status'))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'completed' => 'success',
                                        'failed' => 'danger',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => __('filament.resources.transaction.status_options.'.$state)),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament.resources.transaction.sections.customer_information'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('customer_name')
                                    ->label(__('filament.resources.transaction.fields.customer_name')),
                                Infolists\Components\TextEntry::make('customer_email')
                                    ->label(__('filament.resources.transaction.fields.customer_email')),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament.resources.transaction.sections.payment_information'))
                    ->schema([
                        Infolists\Components\Grid::make(3)
                            ->schema([
                                Infolists\Components\TextEntry::make('amount')
                                    ->label(__('filament.resources.transaction.fields.amount'))
                                    ->money('VND'),
                                Infolists\Components\TextEntry::make('currency')
                                    ->label(__('filament.resources.transaction.fields.currency')),
                                Infolists\Components\TextEntry::make('payment_method')
                                    ->label(__('filament.resources.transaction.fields.payment_method')),
                            ]),
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('charge_id')
                                    ->label(__('filament.resources.transaction.fields.charge_id')),
                                Infolists\Components\TextEntry::make('order_id')
                                    ->label(__('filament.resources.transaction.fields.order_id')),
                            ]),
                    ]),

                Infolists\Components\Section::make(__('filament.resources.transaction.sections.timestamps'))
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label(__('filament.common.fields.created_at'))
                                    ->dateTime(),
                                Infolists\Components\TextEntry::make('updated_at')
                                    ->label(__('filament.common.fields.updated_at'))
                                    ->dateTime(),
                            ]),
                    ])
                    ->collapsible(),
            ]);
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
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }

    /**
     * Disable create capabilities for transactions.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Disable edit capabilities for transactions.
     */
    public static function canEdit($record): bool
    {
        return false;
    }

    /**
     * Disable delete capabilities for transactions.
     */
    public static function canDelete($record): bool
    {
        return false;
    }

    /**
     * Disable delete any capabilities for transactions.
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }
}
