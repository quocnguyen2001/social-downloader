<?php

namespace App\Filament\Resources;

use App\Enums\DownloadSessionStatus;
use App\Filament\Components\SharedActions;
use App\Filament\Components\SharedColors;
use App\Filament\Components\SharedFilters;
use App\Filament\Components\SharedFormComponents;
use App\Filament\Resources\DownloadSessionResource\Pages;
use App\Filament\Resources\DownloadSessionResource\RelationManagers\DownloadOptionsRelationManager;
use App\Models\ApiKey;
use App\Models\DownloadSession;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DownloadSessionResource extends Resource
{
    protected static ?string $model = DownloadSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = null;

    protected static ?string $navigationLabel = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.downloads');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.download_sessions');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.download_session.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.download_session.plural_label');
    }

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.download_session.sections.session_information'))
                    ->schema([
                        Forms\Components\Select::make('api_key_id')
                            ->label(trans('messages.table.columns.api_key'))
                            ->options(ApiKey::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        SharedFormComponents::urlInput(),

                        SharedFormComponents::platformSelect(),

                        SharedFormComponents::downloadSessionStatusSelect(),
                    ])->columns(2),

                Forms\Components\Section::make(__('filament.resources.download_session.sections.video_details'))
                    ->schema([
                        Forms\Components\TextInput::make('video_id')
                            ->maxLength(255)
                            ->label(__('filament.resources.download_session.fields.video_id')),

                        Forms\Components\TextInput::make('title')
                            ->maxLength(500)
                            ->label(__('filament.resources.download_session.fields.title'))
                            ->placeholder(trans('messages.placeholders.enter_video_title')),

                        Forms\Components\TextInput::make('thumbnail_path')
                            ->url()
                            ->maxLength(1000)
                            ->label(__('filament.resources.download_session.fields.thumbnail_path')),

                        Forms\Components\TextInput::make('duration')
                            ->numeric()
                            ->label(__('filament.resources.download_session.fields.duration')),
                    ])->columns(2),

                Forms\Components\Section::make(__('filament.resources.download_session.sections.status_errors'))
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label(__('filament.resources.download_session.fields.error_message'))
                            ->placeholder(trans('messages.placeholders.enter_error_message')),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('filament.resources.download_session.fields.expires_at')),
                    ])->columns(1),
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

                Tables\Columns\BadgeColumn::make('platform')
                    ->label(__('filament.resources.download_session.fields.platform'))
                    ->colors(SharedColors::platform())
                    ->icons(SharedColors::platformIcons())
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label(__('filament.resources.download_session.fields.title'))
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (DownloadSession $record): ?string {
                        return $record->title;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(__('filament.resources.download_session.fields.status'))
                    ->colors(SharedColors::downloadSessionStatus())
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label(__('filament.resources.download_session.fields.duration'))
                    ->sortable('duration')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('time_until_expiration')
                    ->label(__('filament.resources.download_session.fields.expires_at'))
                    ->sortable('expires_at')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.common.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SharedFilters::apiKeyFilter(),

                SharedFilters::platformFilter(),

                SharedFilters::downloadSessionStatusFilter(),

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
                Tables\Actions\Action::make('start_fetching')
                    ->label(__('filament.resources.download_session.actions.start_fetching'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsFetchingMetadata();
                        Notification::make()
                            ->title(__('filament.resources.download_session.messages.started_fetching'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::PENDING),

                Tables\Actions\Action::make('mark_metadata_fetched')
                    ->label(__('filament.resources.download_session.actions.mark_metadata_fetched'))
                    ->icon('heroicon-o-document-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsMetadataFetched();
                        Notification::make()
                            ->title(__('filament.resources.download_session.messages.metadata_fetched'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::FETCHING_METADATA),

                Tables\Actions\Action::make('mark_ready_for_download')
                    ->label(__('filament.resources.download_session.actions.mark_ready'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsReadyForDownload();
                        Notification::make()
                            ->title(__('filament.resources.download_session.messages.ready_for_download'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::METADATA_FETCHED),

                SharedActions::markAsFailedAction(),

                SharedActions::markAsExpiredAction(),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('cleanup_expired')
                        ->label(__('filament.resources.download_session.actions.cleanup_expired'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $expiredCount = $records->filter(fn ($record) => $record->isExpired())->count();
                            $records->filter(fn ($record) => $record->isExpired())->each->delete();

                            Notification::make()
                                ->title(__('filament.resources.download_session.messages.cleaned_expired', ['count' => $expiredCount]))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reset_to_pending')
                        ->label(__('filament.resources.download_session.actions.reset_to_pending'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            $resetSessions = $records->filter(fn ($record) => $record->isFailed());
                            $resetSessions->each(function ($record) {
                                $record->update([
                                    'status' => DownloadSessionStatus::PENDING,
                                    'error_message' => null,
                                ]);
                            });

                            Notification::make()
                                ->title(__('filament.resources.download_session.messages.reset_sessions', ['count' => $resetSessions->count()]))
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
            DownloadOptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloadSessions::route('/'),
            'view' => Pages\ViewDownloadSession::route('/{record}'),
        ];
    }

    /**
     * Disable edit capabilities for download sessions.
     */
    public static function canEdit($record): bool
    {
        return false;
    }
}
