<?php

namespace App\Filament\Resources;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DownloadSessionResource extends Resource
{
    protected static ?string $model = DownloadSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationGroup = 'Download Management';

    protected static ?string $navigationLabel = 'Download Sessions';

    protected static ?string $modelLabel = 'Download Session';

    protected static ?string $pluralModelLabel = 'Download Sessions';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Session Information')
                    ->schema([
                        Forms\Components\Select::make('api_key_id')
                            ->label(trans('messages.table.columns.api_key'))
                            ->options(ApiKey::pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Forms\Components\TextInput::make('original_url')
                            ->url()
                            ->required()
                            ->maxLength(1000)
                            ->label(trans('messages.labels.original_url'))
                            ->placeholder(trans('messages.placeholders.enter_original_url')),

                        Forms\Components\Select::make('platform')
                            ->label(trans('messages.labels.platform'))
                            ->options(Platform::getOptions())
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label(trans('messages.labels.status'))
                            ->options(DownloadSessionStatus::getOptions())
                            ->default(DownloadSessionStatus::PENDING->value)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Video Details')
                    ->schema([
                        Forms\Components\TextInput::make('video_id')
                            ->maxLength(255)
                            ->label(trans('messages.labels.video_id')),

                        Forms\Components\TextInput::make('title')
                            ->maxLength(500)
                            ->label(trans('messages.labels.title'))
                            ->placeholder(trans('messages.placeholders.enter_video_title')),

                        Forms\Components\TextInput::make('thumbnail_path')
                            ->url()
                            ->maxLength(1000)
                            ->label(trans('messages.labels.thumbnail_url')),

                        Forms\Components\TextInput::make('duration')
                            ->numeric()
                            ->label(trans('messages.labels.duration')),
                    ])->columns(2),

                Forms\Components\Section::make('Status & Errors')
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label(trans('messages.labels.error_message'))
                            ->placeholder(trans('messages.placeholders.enter_error_message')),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(trans('messages.labels.expires_at')),
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

                Tables\Columns\TextColumn::make('title')
                    ->label(trans('messages.table.columns.video_title'))
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (DownloadSession $record): ?string {
                        return $record->title;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(trans('messages.table.columns.status'))
                    ->colors([
                        'warning' => DownloadSessionStatus::PENDING->value,
                        'info' => DownloadSessionStatus::FETCHING_METADATA->value,
                        'primary' => DownloadSessionStatus::METADATA_FETCHED->value,
                        'success' => DownloadSessionStatus::READY_FOR_DOWNLOAD->value,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_duration')
                    ->label('Duration')
                    ->sortable('duration')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('time_until_expiration')
                    ->label('Expires')
                    ->sortable('expires_at')
                    ->toggleable(),

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

                SelectFilter::make('platform')
                    ->options([
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok',
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                    ]),

                SelectFilter::make('status')
                    ->options(DownloadSessionStatus::getOptions()),

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
                    ->label('Start Fetching Metadata')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsFetchingMetadata();
                        Notification::make()
                            ->title('Started fetching metadata')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::PENDING),

                Tables\Actions\Action::make('mark_metadata_fetched')
                    ->label('Mark Metadata Fetched')
                    ->icon('heroicon-o-document-check')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsMetadataFetched();
                        Notification::make()
                            ->title('Metadata marked as fetched')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::FETCHING_METADATA),

                Tables\Actions\Action::make('mark_ready_for_download')
                    ->label('Mark Ready for Download')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsReadyForDownload();
                        Notification::make()
                            ->title('Session marked as ready for download')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::METADATA_FETCHED),

                Tables\Actions\Action::make('mark_failed')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->form([
                        Forms\Components\Textarea::make('error_message')
                            ->required()
                            ->label('Error Message'),
                    ])
                    ->action(function (DownloadSession $record, array $data) {
                        $record->markAsFailed($data['error_message']);
                        Notification::make()
                            ->title('Download session marked as failed')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => in_array($record->status, [DownloadSessionStatus::PENDING, DownloadSessionStatus::FETCHING_METADATA])),

                Tables\Actions\Action::make('mark_expired')
                    ->icon('heroicon-o-clock')
                    ->color('secondary')
                    ->requiresConfirmation()
                    ->action(function (DownloadSession $record) {
                        $record->markAsExpired();
                        Notification::make()
                            ->title('Download session marked as expired')
                            ->warning()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => ! $record->isExpired()),

                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    Tables\Actions\BulkAction::make('cleanup_expired')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $expiredCount = $records->filter(fn ($record) => $record->isExpired())->count();
                            $records->filter(fn ($record) => $record->isExpired())->each->delete();

                            Notification::make()
                                ->title("Cleaned up {$expiredCount} expired sessions")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('reset_to_pending')
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
                                ->title("Reset {$resetSessions->count()} sessions to pending")
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
