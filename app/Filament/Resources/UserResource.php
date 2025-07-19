<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\MembershipPlan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * User Resource for Filament Admin Panel.
 * 
 * Manages users with membership plans and comprehensive user information.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Users';
    }

    public static function getModelLabel(): string
    {
        return 'User';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Users';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->nullable(),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Membership Information')
                    ->schema([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label('Membership Plan')
                            ->relationship('membershipPlan', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required(),
                            ]),

                        Forms\Components\DateTimePicker::make('membership_started_at')
                            ->label('Membership Started')
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('membership_expires_at')
                            ->label('Membership Expires')
                            ->nullable()
                            ->helperText('Leave empty for lifetime membership'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Email copied to clipboard'),

                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label('Plan')
                    ->badge()
                    ->color(fn ($record) => match ($record->membershipPlan?->slug) {
                        'free' => 'gray',
                        'basic' => 'info',
                        'pro' => 'success',
                        'premium' => 'warning',
                        'enterprise' => 'danger',
                        default => 'gray',
                    })
                    ->default('No Plan'),

                Tables\Columns\TextColumn::make('membership_expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record?->hasMembershipExpired() ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state ? $state->format('M j, Y') : 'Never'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('api_requests_count')
                    ->label('API Requests')
                    ->counts('apiRequests')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('download_sessions_count')
                    ->label('Downloads')
                    ->counts('downloadSessions')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('membership_plan_id')
                    ->label('Membership Plan')
                    ->relationship('membershipPlan', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label('Email Verified')
                    ->nullable(),

                Tables\Filters\Filter::make('membership_expired')
                    ->label('Membership Expired')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('membership_expires_at')
                              ->where('membership_expires_at', '<', now())
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from'),
                        Forms\Components\DatePicker::make('created_until'),
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
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('verify_email')
                        ->label('Verify Email')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn (User $record) => $record->update(['email_verified_at' => now()]))
                        ->visible(fn (User $record) => !$record->email_verified_at)
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('extend_membership')
                        ->label('Extend Membership')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->form([
                            Forms\Components\DateTimePicker::make('new_expiry')
                                ->label('New Expiry Date')
                                ->required()
                                ->default(fn (User $record) => 
                                    $record->membership_expires_at?->addMonth() ?? now()->addMonth()
                                ),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->update(['membership_expires_at' => $data['new_expiry']]);
                        })
                        ->visible(fn (User $record) => $record->membershipPlan),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('verify_emails')
                        ->label('Verify Emails')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['email_verified_at' => now()]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('assign_plan')
                        ->label('Assign Plan')
                        ->icon('heroicon-o-credit-card')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('membership_plan_id')
                                ->label('Membership Plan')
                                ->options(MembershipPlan::active()->pluck('name', 'id'))
                                ->required(),
                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label('Expires At')
                                ->nullable(),
                        ])
                        ->action(function ($records, array $data) {
                            $records->each->update([
                                'membership_plan_id' => $data['membership_plan_id'],
                                'membership_started_at' => now(),
                                'membership_expires_at' => $data['expires_at'],
                            ]);
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DownloadSessionsRelationManager::class,
            RelationManagers\ApiRequestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['apiRequests', 'downloadSessions']);
    }
}
