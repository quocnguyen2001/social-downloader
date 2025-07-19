<?php

namespace App\Filament\Resources;

use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use App\Filament\Resources\DownloadSessionResource\Pages;
use App\Models\DownloadSession;
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

                        Forms\Components\TextInput::make('thumbnail_url')
                            ->url()
                            ->maxLength(1000)
                            ->label(trans('messages.labels.thumbnail_url')),

                        Forms\Components\TextInput::make('duration')
                            ->numeric()
                            ->label(trans('messages.labels.duration')),
                    ])->columns(2),

                Forms\Components\Section::make('Download Settings')
                    ->schema([
                        Forms\Components\Select::make('quality')
                            ->label(trans('messages.labels.quality'))
                            ->options(VideoQuality::getOptions())
                            ->required(),

                        Forms\Components\Select::make('format')
                            ->label(trans('messages.labels.format'))
                            ->options(VideoFormat::getOptions())
                            ->required(),

                        Forms\Components\TextInput::make('file_size')
                            ->numeric()
                            ->label(trans('messages.labels.file_size'))
                            ->placeholder(trans('messages.placeholders.enter_file_size')),

                        Forms\Components\TextInput::make('download_url')
                            ->url()
                            ->maxLength(1000)
                            ->label(trans('messages.labels.download_url'))
                            ->placeholder(trans('messages.placeholders.enter_download_url')),
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

                Tables\Columns\BadgeColumn::make('quality')
                    ->label(trans('messages.table.columns.quality'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('format')
                    ->label(trans('messages.table.columns.format'))
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label(trans('messages.table.columns.status'))
                    ->colors([
                        'warning' => DownloadSessionStatus::PENDING->value,
                        'info' => DownloadSessionStatus::PROCESSING->value,
                        'success' => DownloadSessionStatus::COMPLETED->value,
                        'danger' => DownloadSessionStatus::FAILED->value,
                        'secondary' => DownloadSessionStatus::EXPIRED->value,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('File Size')
                    ->sortable('file_size')
                    ->toggleable(),

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
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                    ]),

                SelectFilter::make('quality')
                    ->options([
                        '144p' => '144p',
                        '360p' => '360p',
                        '720p' => '720p',
                        '1080p' => '1080p',
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
                Tables\Actions\Action::make('retry')
                    ->label(trans('messages.table.actions.retry'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->action(function (DownloadSession $record) {
                        $record->markAsProcessing();
                        Notification::make()
                            ->title(trans('messages.success.download_retry_queued'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => $record->status === DownloadSessionStatus::FAILED),

                Tables\Actions\Action::make('mark_completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('download_url')
                            ->url()
                            ->required()
                            ->label('Download URL'),
                        Forms\Components\TextInput::make('file_size')
                            ->numeric()
                            ->required()
                            ->label('File Size (bytes)'),
                    ])
                    ->action(function (DownloadSession $record, array $data) {
                        $record->markAsCompleted($data['download_url'], $data['file_size']);
                        Notification::make()
                            ->title('Download session marked as completed')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (DownloadSession $record) => in_array($record->status, ['pending', 'processing'])),

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
                    ->visible(fn (DownloadSession $record) => in_array($record->status, ['pending', 'processing'])),

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
                    ->visible(fn (DownloadSession $record) => $record->status !== 'expired'),

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

                    Tables\Actions\BulkAction::make('retry_failed')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records) {
                            $failedSessions = $records->filter(fn ($record) => $record->status === 'failed');
                            $failedSessions->each->markAsProcessing();

                            Notification::make()
                                ->title("Queued {$failedSessions->count()} failed sessions for retry")
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
