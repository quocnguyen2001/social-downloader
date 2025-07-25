<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\MembershipPlan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
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

                Tables\Columns\TextColumn::make('tokens_count')
                    ->label('API Tokens')
                    ->counts('tokens')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === 0 => 'gray',
                        $state <= 2 => 'success',
                        $state <= 5 => 'warning',
                        default => 'danger',
                    }),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder('Never')
                    ->toggleable(),

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
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('membership_expires_at')
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

                Tables\Filters\SelectFilter::make('has_tokens')
                    ->label('API Tokens')
                    ->options([
                        'with_tokens' => 'Has Tokens',
                        'without_tokens' => 'No Tokens',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with_tokens' => $query->has('tokens'),
                            'without_tokens' => $query->doesntHave('tokens'),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('login_activity')
                    ->label('Login Activity')
                    ->options([
                        'recent' => 'Logged in recently (7 days)',
                        'inactive' => 'Inactive (30+ days)',
                        'never' => 'Never logged in',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'recent' => $query->where('last_login_at', '>=', now()->subDays(7)),
                            'inactive' => $query->where('last_login_at', '<', now()->subDays(30)),
                            'never' => $query->whereNull('last_login_at'),
                            default => $query,
                        };
                    }),

                Tables\Filters\TernaryFilter::make('active_tokens')
                    ->label('Has Active Tokens')
                    ->queries(
                        true: fn (Builder $query) => $query->has('tokens'),
                        false: fn (Builder $query) => $query->doesntHave('tokens'),
                    ),
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
                        ->visible(fn (User $record) => ! $record->email_verified_at)
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('extend_membership')
                        ->label('Extend Membership')
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->form([
                            Forms\Components\DateTimePicker::make('new_expiry')
                                ->label('New Expiry Date')
                                ->required()
                                ->default(fn (User $record) => $record->membership_expires_at?->addMonth() ?? now()->addMonth()
                                ),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->update(['membership_expires_at' => $data['new_expiry']]);
                        })
                        ->visible(fn (User $record) => $record->membershipPlan),
                    Tables\Actions\Action::make('generate_token')
                        ->label('Generate API Token')
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->action(function (User $record) {
                            // Generate token with default settings
                            $tokenName = $record->name.' - '.now()->format('M j, Y g:i A');
                            $token = $record->createToken(
                                $tokenName,
                                ['*'], // All abilities
                                now()->addDays(30) // Expires in 30 days
                            );

                            // Show success notification with the token
                            Notification::make()
                                ->title('API Token Generated Successfully')
                                ->body('Token: '.$token->plainTextToken."\n\nExpires: ".now()->addDays(30)->format('M j, Y g:i A')."\n\n⚠️ Copy this token now - it won't be shown again!")
                                ->success()
                                ->duration(30000) // Show for 30 seconds
                                ->persistent() // Keep until manually dismissed
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Generate API Token')
                        ->modalDescription('This will generate a new API token with full permissions that expires in 30 days. The token will be shown once in a notification.')
                        ->modalSubmitActionLabel('Generate Token'),
                    Tables\Actions\Action::make('manage_tokens')
                        ->label('Manage Tokens')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->color('warning')
                        ->modalContent(function (User $record) {
                            $tokens = $record->tokens()->orderBy('created_at', 'desc')->get();

                            if ($tokens->isEmpty()) {
                                return view('filament.components.no-tokens');
                            }

                            return view('filament.components.token-list', ['tokens' => $tokens, 'user' => $record]);
                        })
                        ->modalActions([
                            \Filament\Actions\Action::make('close')
                                ->label('Close')
                                ->color('gray')
                                ->close(),
                        ])
                        ->modalWidth('4xl')
                        ->modalHeading(fn (User $record) => 'Manage API Tokens for '.$record->name)
                        ->visible(fn (User $record) => $record->tokens()->count() > 0),
                    Tables\Actions\Action::make('revoke_all_tokens')
                        ->label('Revoke All Tokens')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (User $record) {
                            $tokenCount = $record->tokens()->count();
                            $record->tokens()->delete();

                            Notification::make()
                                ->title('All Tokens Revoked')
                                ->body("Successfully revoked {$tokenCount} token(s) for {$record->name}")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Revoke All API Tokens')
                        ->modalDescription(fn (User $record) => "Are you sure you want to revoke all API tokens for {$record->name}? This action cannot be undone.")
                        ->modalSubmitActionLabel('Revoke All Tokens')
                        ->visible(fn (User $record) => $record->tokens()->count() > 0),
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
                    Tables\Actions\BulkAction::make('bulk_generate_tokens')
                        ->label('Generate API Tokens')
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('token_name_prefix')
                                ->label('Token Name Prefix')
                                ->required()
                                ->default('Bulk Generated')
                                ->helperText('Each token will be named: [Prefix] - [User Name] - [Date]'),
                            Forms\Components\Select::make('abilities')
                                ->label('Token Abilities')
                                ->multiple()
                                ->options([
                                    '*' => 'All Abilities',
                                    'auth:user' => 'User Profile Access',
                                    'auth:logout' => 'Logout Access',
                                ])
                                ->default(['*'])
                                ->helperText('Select what these tokens can do'),
                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label('Expires At')
                                ->nullable()
                                ->default(now()->addDays(30))
                                ->helperText('Leave empty for no expiration'),
                        ])
                        ->action(function ($records, array $data) {
                            $generatedCount = 0;
                            $expiresAt = $data['expires_at'] ? \Carbon\Carbon::parse($data['expires_at']) : null;

                            foreach ($records as $user) {
                                $tokenName = $data['token_name_prefix'].' - '.$user->name.' - '.now()->format('M j, Y');
                                $user->createToken($tokenName, $data['abilities'], $expiresAt);
                                $generatedCount++;
                            }

                            Notification::make()
                                ->title('Bulk Token Generation Complete')
                                ->body("Successfully generated {$generatedCount} API token(s)")
                                ->success()
                                ->send();
                        })
                        ->modalWidth('lg')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Generate API Tokens')
                        ->modalDescription('Generate API tokens for all selected users. Each user will receive one token with the specified settings.')
                        ->modalSubmitActionLabel('Generate Tokens'),
                    Tables\Actions\BulkAction::make('bulk_revoke_tokens')
                        ->label('Revoke All Tokens')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function ($records) {
                            $totalRevoked = 0;

                            foreach ($records as $user) {
                                $tokenCount = $user->tokens()->count();
                                $user->tokens()->delete();
                                $totalRevoked += $tokenCount;
                            }

                            Notification::make()
                                ->title('Bulk Token Revocation Complete')
                                ->body("Successfully revoked {$totalRevoked} API token(s) from ".$records->count().' user(s)')
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Revoke All API Tokens')
                        ->modalDescription('Are you sure you want to revoke ALL API tokens for the selected users? This action cannot be undone.')
                        ->modalSubmitActionLabel('Revoke All Tokens'),
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
