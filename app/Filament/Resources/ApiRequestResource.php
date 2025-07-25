<?php

namespace App\Filament\Resources;

use App\Enums\Platform;
use App\Filament\Resources\ApiRequestResource\Pages;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiRequestResource extends Resource
{
    protected static ?string $model = ApiRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'API Management';

    protected static ?string $navigationLabel = 'API Requests';

    protected static ?string $modelLabel = 'API Request';

    protected static ?string $pluralModelLabel = 'API Requests';

    protected static ?int $navigationSort = 2;

    // This is a read-only resource - no create/edit forms
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Details')
                    ->schema([
                        Forms\Components\TextInput::make('endpoint')
                            ->label(trans('messages.labels.endpoint'))
                            ->disabled(),
                        Forms\Components\TextInput::make('method')
                            ->label(trans('messages.labels.method'))
                            ->disabled(),
                        Forms\Components\TextInput::make('ip_address')
                            ->label(trans('messages.labels.ip_address'))
                            ->disabled(),
                        Forms\Components\Textarea::make('user_agent')
                            ->label(trans('messages.labels.user_agent'))
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Video Details')
                    ->schema([
                        Forms\Components\TextInput::make('original_url')
                            ->disabled(),
                        Forms\Components\TextInput::make('platform')
                            ->disabled(),
                        Forms\Components\TextInput::make('video_title')
                            ->disabled(),
                        Forms\Components\TextInput::make('requested_quality')
                            ->disabled(),
                        Forms\Components\TextInput::make('requested_format')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Response Details')
                    ->schema([
                        Forms\Components\TextInput::make('status_code')
                            ->disabled(),
                        Forms\Components\TextInput::make('response_time')
                            ->disabled(),
                        Forms\Components\TextInput::make('file_size')
                            ->disabled(),
                        Forms\Components\TextInput::make('download_url')
                            ->disabled(),
                        Forms\Components\TextInput::make('cost')
                            ->disabled(),
                        Forms\Components\Toggle::make('billed')
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
                    ->colors([
                        'danger' => Platform::YOUTUBE->value,
                        'warning' => Platform::TIKTOK->value,
                        'success' => Platform::INSTAGRAM->value,
                        'primary' => Platform::FACEBOOK->value,
                    ])
                    ->icons([
                        'heroicon-o-play' => Platform::YOUTUBE->value,
                        'heroicon-o-musical-note' => Platform::TIKTOK->value,
                        'heroicon-o-camera' => Platform::INSTAGRAM->value,
                        'heroicon-o-users' => Platform::FACEBOOK->value,
                    ])
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status_code')
                    ->label('Status')
                    ->colors([
                        'success' => 200,
                        'warning' => fn ($state) => $state >= 400 && $state < 500,
                        'danger' => fn ($state) => $state >= 500,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('video_title')
                    ->label('Video Title')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_quality')
                    ->label('Quality')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('requested_format')
                    ->label('Format')
                    ->badge()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_response_time')
                    ->label('Response Time')
                    ->sortable('response_time')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('File Size')
                    ->sortable('file_size')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formatted_cost')
                    ->label('Cost')
                    ->sortable('cost'),

                Tables\Columns\IconColumn::make('billed')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('api_key_id')
                    ->label(trans('messages.table.columns.api_key'))
                    ->options(ApiKey::pluck('name', 'id'))
                    ->searchable(),

                SelectFilter::make('platform')
                    ->label(trans('messages.table.filters.platform'))
                    ->options(Platform::getOptions()),

                SelectFilter::make('status_code')
                    ->label(trans('messages.table.filters.status_code'))
                    ->options([
                        '200' => trans('messages.filters.success'),
                        '400' => trans('messages.filters.bad_request'),
                        '404' => trans('messages.filters.not_found'),
                        '500' => trans('messages.filters.server_error'),
                    ]),

                SelectFilter::make('billed')
                    ->label(trans('messages.table.filters.billed'))
                    ->options([
                        '1' => trans('messages.filters.billed'),
                        '0' => trans('messages.filters.not_billed'),
                    ]),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created from'),
                        DatePicker::make('created_until')
                            ->label('Created until'),
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
