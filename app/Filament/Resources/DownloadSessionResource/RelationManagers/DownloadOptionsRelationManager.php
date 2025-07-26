<?php

namespace App\Filament\Resources\DownloadSessionResource\RelationManagers;

use App\Enums\DownloadOptionStatus;
use App\Enums\DownloadOptionType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DownloadOptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'downloadOptions';

    protected static ?string $recordTitleAttribute = 'quality';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Content Information')
                    ->schema([
                        Forms\Components\TextInput::make('cdn_id')
                            ->label(__('models.download_option.fields.cdn_id'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('mime_type')
                            ->label(__('models.download_option.fields.mime_type'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('file_size')
                            ->label(__('models.download_option.fields.file_size'))
                            ->numeric()
                            ->suffix('bytes'),

                        Forms\Components\TextInput::make('estimated_download_time')
                            ->label(__('models.download_option.fields.estimated_download_time'))
                            ->numeric()
                            ->suffix('seconds'),
                    ])->columns(2),

                Forms\Components\Section::make('Storage Information')
                    ->schema([
                        Forms\Components\TextInput::make('storage_disk')
                            ->label(__('models.download_option.fields.storage_disk'))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('storage_file_path')
                            ->label(__('models.download_option.fields.storage_file_path'))
                            ->maxLength(255),

                        Forms\Components\Textarea::make('download_cdn_url')
                            ->label(__('models.download_option.fields.download_cdn_url'))
                            ->maxLength(1000)
                            ->rows(2),
                    ])->columns(2),

                Forms\Components\Section::make('Content Specifications')
                    ->schema([
                        Forms\Components\TextInput::make('quality')
                            ->label(__('models.download_option.fields.quality'))
                            ->required()
                            ->maxLength(255)
                            ->placeholder('144p, 360p, 720p, 1080p, etc.'),

                        Forms\Components\Select::make('type')
                            ->label(__('models.download_option.fields.type'))
                            ->options(DownloadOptionType::getOptions())
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->label(__('models.download_option.fields.status'))
                            ->options(DownloadOptionStatus::getOptions())
                            ->required()
                            ->default(DownloadOptionStatus::CDN->value),
                    ])->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quality')
            ->columns([
                Tables\Columns\TextColumn::make('quality')
                    ->label(__('models.download_option.fields.quality'))
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label(__('models.download_option.fields.type'))
                    ->badge()
                    ->color(fn (DownloadOptionType $state): string => $state->getColor())
                    ->icon(fn (DownloadOptionType $state): string => $state->getIcon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label(__('models.download_option.fields.status'))
                    ->badge()
                    ->color(fn (DownloadOptionStatus $state): string => $state->getColor())
                    ->icon(fn (DownloadOptionStatus $state): string => $state->getIcon())
                    ->sortable(),

                Tables\Columns\TextColumn::make('file_size')
                    ->label(__('models.download_option.fields.file_size'))
                    ->formatStateUsing(fn ($record) => $record->formatted_file_size)
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_download_time')
                    ->label(__('models.download_option.fields.estimated_download_time'))
                    ->formatStateUsing(fn ($record) => $record->formatted_estimated_time)
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label(__('models.download_option.fields.mime_type'))
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_available')
                    ->label(__('models.download_option.fields.is_available'))
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isAvailable())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('models.download_option.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('models.download_option.fields.type'))
                    ->options(DownloadOptionType::getOptions()),

                Tables\Filters\SelectFilter::make('status')
                    ->label(__('models.download_option.fields.status'))
                    ->options(DownloadOptionStatus::getOptions()),

                Tables\Filters\Filter::make('available_only')
                    ->label(__('messages.filters.available_only'))
                    ->query(fn ($query) => $query->where(function ($q) {
                        $q->where('status', DownloadOptionStatus::DOWNLOADED->value)
                            ->whereNotNull('storage_disk')
                            ->whereNotNull('storage_file_path')
                            ->orWhere(function ($q2) {
                                $q2->where('status', DownloadOptionStatus::CDN->value)
                                    ->whereNotNull('download_cdn_url');
                            });
                    }))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('messages.actions.create_download_option')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Tables\Actions\Action::make('download')
                    ->label(__('messages.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn ($record) => $record->getDownloadUrl())
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => $record->isAvailable()),

                Tables\Actions\Action::make('mark_downloaded')
                    ->label(__('messages.actions.mark_downloaded'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('storage_disk')
                            ->label(__('models.download_option.fields.storage_disk'))
                            ->required()
                            ->default('public'),
                        Forms\Components\TextInput::make('storage_file_path')
                            ->label(__('models.download_option.fields.storage_file_path'))
                            ->required(),
                        Forms\Components\TextInput::make('file_size')
                            ->label(__('models.download_option.fields.file_size'))
                            ->numeric()
                            ->suffix('bytes'),
                    ])
                    ->action(function ($record, array $data) {
                        $record->markAsDownloaded(
                            $data['storage_disk'],
                            $data['storage_file_path'],
                            $data['file_size'] ?? null
                        );
                    })
                    ->visible(fn ($record) => $record->status === DownloadOptionStatus::CDN),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('quality', 'asc');
    }
}
