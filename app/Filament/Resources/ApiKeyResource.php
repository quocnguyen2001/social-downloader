<?php

namespace App\Filament\Resources;

use App\Filament\Components\SharedActions;
use App\Filament\Components\SharedColors;
use App\Filament\Components\SharedFilters;
use App\Filament\Components\SharedFormComponents;
use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'API Management';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return trans('messages.navigation.api_keys');
    }

    public static function getModelLabel(): string
    {
        return trans('models.api_key.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('models.api_key.plural');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(trans('messages.sections.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label(trans('messages.labels.application_name'))
                            ->placeholder(trans('messages.placeholders.enter_application_name')),

                        Forms\Components\TextInput::make('company_name')
                            ->maxLength(255)
                            ->label(trans('messages.labels.company_name'))
                            ->placeholder(trans('messages.placeholders.enter_company_name')),

                        SharedFormComponents::apiKeyStatusSelect(),
                    ])->columns(2),

                Forms\Components\Section::make(trans('messages.sections.limits_pricing'))
                    ->schema([
                        Forms\Components\TextInput::make('daily_limit')
                            ->numeric()
                            ->default(1000)
                            ->required()
                            ->label(trans('messages.labels.daily_limit'))
                            ->placeholder(trans('messages.placeholders.enter_daily_limit'))
                            ->helperText(trans('messages.descriptions.daily_limit_description')),

                        Forms\Components\TextInput::make('monthly_limit')
                            ->numeric()
                            ->default(30000)
                            ->required()
                            ->label(trans('messages.labels.monthly_limit'))
                            ->placeholder(trans('messages.placeholders.enter_monthly_limit'))
                            ->helperText(trans('messages.descriptions.monthly_limit_description')),

                        Forms\Components\TextInput::make('price_per_request')
                            ->numeric()
                            ->step(0.0001)
                            ->default(0.0500)
                            ->required()
                            ->label(trans('messages.labels.price_per_request'))
                            ->placeholder(trans('messages.placeholders.enter_price_per_request'))
                            ->helperText(trans('messages.descriptions.price_per_request_description')),
                    ])->columns(3),

                Forms\Components\Section::make(trans('messages.sections.api_key_section'))
                    ->schema([
                        Forms\Components\TextInput::make('key_hash')
                            ->label(trans('models.api_key.fields.key_hash'))
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(trans('messages.table.columns.name')),

                Tables\Columns\TextColumn::make('company_name')
                    ->searchable()
                    ->sortable()
                    ->label(trans('messages.table.columns.company'))
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors(SharedColors::apiKeyStatus())
                    ->sortable()
                    ->label(trans('messages.table.columns.status')),

                Tables\Columns\TextColumn::make('daily_usage_display')
                    ->label(trans('messages.table.columns.daily_usage'))
                    ->getStateUsing(fn (ApiKey $record) => trans('messages.info.usage_display', [
                        'usage' => $record->daily_usage,
                        'limit' => $record->daily_limit,
                    ]))
                    ->badge()
                    ->color(fn (ApiKey $record) => SharedColors::getUsageLevelColor($record->daily_usage_percentage)),

                Tables\Columns\TextColumn::make('monthly_usage_display')
                    ->label(trans('messages.table.columns.monthly_usage'))
                    ->getStateUsing(fn (ApiKey $record) => trans('messages.info.usage_display', [
                        'usage' => $record->monthly_usage,
                        'limit' => $record->monthly_limit,
                    ]))
                    ->badge()
                    ->color(fn (ApiKey $record) => SharedColors::getUsageLevelColor($record->monthly_usage_percentage)),

                Tables\Columns\TextColumn::make('total_usage')
                    ->label(trans('messages.table.columns.total_usage'))
                    ->numeric()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price_per_request')
                    ->label(trans('messages.table.columns.price_request'))
                    ->money('VND', divideBy: 1)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label(trans('messages.table.columns.contact'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(trans('messages.labels.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SharedFilters::apiKeyStatusFilter(),

                SharedFilters::dateRangeFilter(),
            ])
            ->actions([
                SharedActions::activateApiKeyAction(),

                SharedActions::deactivateApiKeyAction(),

                SharedActions::suspendApiKeyAction(),

                Tables\Actions\Action::make('reset_usage')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (ApiKey $record) {
                        $record->update([
                            'daily_usage' => 0,
                            'monthly_usage' => 0,
                            'last_reset_daily' => now()->toDateString(),
                            'last_reset_monthly' => now()->startOfMonth()->toDateString(),
                        ]);
                        Notification::make()
                            ->title(trans('messages.success.usage_reset'))
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('generate_new_key')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('This will generate a new API key and invalidate the current one.')
                    ->action(function (ApiKey $record) {
                        $newKey = ApiKey::generateKey();
                        $record->update(['key_hash' => hash('sha256', $newKey)]);

                        Notification::make()
                            ->title(trans('messages.success.new_api_key_generated'))
                            ->body("New key: {$newKey}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    SharedActions::bulkActivateApiKeysAction(),

                    SharedActions::bulkDeactivateApiKeysAction(),
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
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'view' => Pages\ViewApiKey::route('/{record}'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
