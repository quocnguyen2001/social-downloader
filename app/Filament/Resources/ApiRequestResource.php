<?php

namespace App\Filament\Resources;

use App\Filament\Components\SharedColors;
use App\Filament\Components\SharedFilters;
use App\Filament\Resources\ApiRequestResource\Pages;
use App\Models\ApiRequest;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiRequestResource extends Resource
{
    protected static ?string $model = ApiRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.api_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.api_requests');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.api_request.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.api_request.plural_label');
    }

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.api_request.sections.request_details'))
                    ->schema([
                        Forms\Components\TextInput::make('endpoint')
                            ->label(__('filament.resources.api_request.fields.endpoint'))
                            ->disabled(),
                        Forms\Components\TextInput::make('method')
                            ->label(__('filament.resources.api_request.fields.method'))
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label(__('filament.resources.api_request.fields.ip_address'))
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label(__('filament.resources.api_request.fields.user_agent'))
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make(__('filament.resources.api_request.sections.video_details'))
                    ->schema([
                        Forms\Components\TextInput::make('original_url')
                            ->label(__('filament.resources.api_request.fields.original_url'))
                            ->disabled(),
                        Forms\Components\TextInput::make('platform')
                            ->label(__('filament.resources.api_request.fields.platform'))
                            ->disabled(),
                        Forms\Components\TextInput::make('video_title')
                            ->label(__('filament.resources.api_request.fields.video_title'))
                            ->disabled(),
                        Forms\Components\TextInput::make('requested_quality')
                            ->label(__('filament.resources.api_request.fields.requested_quality'))
                            ->disabled(),
                        Forms\Components\TextInput::make('requested_format')
                            ->label(__('filament.resources.api_request.fields.requested_format'))
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make(__('filament.resources.api_request.sections.response_details'))
                    ->schema([
                        Forms\Components\TextInput::make('status_code')
                            ->label(__('filament.resources.api_request.fields.status_code'))
                            ->disabled(),
                        Forms\Components\TextInput::make('response_time')
                            ->label(__('filament.resources.api_request.fields.response_time'))
                            ->disabled(),
                        Forms\Components\TextInput::make('file_size')
                            ->label(__('filament.resources.api_request.fields.file_size'))
                            ->disabled(),
                        Forms\Components\TextInput::make('download_url')
                            ->label(__('filament.resources.api_request.fields.download_url'))
                            ->disabled(),
                        Forms\Components\TextInput::make('cost')
                            ->label(__('filament.resources.api_request.fields.cost'))
                            ->disabled(),
                        Forms\Components\Toggle::make('billed')
                            ->label(__('filament.resources.api_request.fields.billed'))
                            ->disabled(),
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

                Tables\Columns\TextColumn::make('endpoint')
                    ->label(trans('messages.table.columns.endpoint'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('platform')
                    ->label(trans('messages.table.columns.platform'))
                    ->colors(SharedColors::platform())
                    ->icons(SharedColors::platformIcons())
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status_code')
                    ->label(__('filament.resources.api_request.columns.status'))
                    ->colors([
                        'success' => 200,
                        'warning' => fn ($state) => $state >= 400 && $state < 500,
                        'danger' => fn ($state) => $state >= 500,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('video_title')
                    ->label(__('filament.resources.api_request.columns.video_title'))
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_quality')
                    ->label(__('filament.resources.api_request.columns.quality'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_format')
                    ->label(__('filament.resources.api_request.columns.format'))
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_response_time')
                    ->label(__('filament.resources.api_request.columns.response_time'))
                    ->sortable('response_time')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label(__('filament.resources.api_request.columns.file_size'))
                    ->sortable('file_size')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_cost')
                    ->label(__('filament.resources.api_request.columns.cost'))
                    ->sortable('cost'),

                Tables\Columns\IconColumn::make('billed')
                    ->label(__('filament.resources.api_request.columns.billed'))
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SharedFilters::apiKeyFilter(),

                SharedFilters::platformFilter(),

                SharedFilters::statusCodeFilter(),

                SharedFilters::billedFilter(),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label(__('filament.resources.api_request.filters.created_from')),
                        DatePicker::make('created_until')
                            ->label(__('filament.resources.api_request.filters.created_until')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions for read-only resource
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable();
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
            'index' => Pages\ListApiRequests::route('/'),
            'view' => Pages\ViewApiRequest::route('/{record}'),
        ];
    }

    // Disable create and edit capabilities
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
