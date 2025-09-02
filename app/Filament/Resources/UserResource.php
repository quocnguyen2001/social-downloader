<?php

namespace App\Filament\Resources;

use App\Filament\Components\SharedColors;
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

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.user_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.users');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.user.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.user.sections.user_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('filament.resources.user.fields.name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label(__('filament.resources.user.fields.email'))
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label(__('filament.resources.user.fields.email_verified_at'))
                            ->nullable(),

                        Forms\Components\TextInput::make('password')
                            ->label(__('filament.resources.user.fields.password'))
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.resources.user.sections.membership_information'))
                    ->schema([
                        Forms\Components\Select::make('membership_plan_id')
                            ->label(__('filament.resources.user.fields.membership_plan'))
                            ->relationship('membershipPlan', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('filament.resources.user.fields.name'))
                                    ->required(),
                                Forms\Components\TextInput::make('slug')
                                    ->required(),
                                Forms\Components\TextInput::make('price')
                                    ->numeric()
                                    ->required(),
                            ]),

                        Forms\Components\DateTimePicker::make('membership_started_at')
                            ->label(__('filament.resources.user.fields.membership_started'))
                            ->nullable(),

                        Forms\Components\DateTimePicker::make('membership_expires_at')
                            ->label(__('filament.resources.user.fields.membership_expires'))
                            ->nullable()
                            ->helperText(__('filament.resources.user.messages.lifetime_membership')),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('filament.resources.user.columns.id'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.resources.user.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label(__('filament.resources.user.columns.email'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage(__('filament.resources.user.messages.email_copied')),

                Tables\Columns\TextColumn::make('membershipPlan.name')
                    ->label(__('filament.resources.user.columns.plan'))
                    ->badge()
                    ->color(fn ($record) => SharedColors::membershipPlanType()[$record->membershipPlan?->slug] ?? 'gray')
                    ->default(__('filament.resources.user.messages.no_plan')),

                Tables\Columns\TextColumn::make('membership_expires_at')
                    ->label(__('filament.resources.user.columns.expires'))
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($record) => $record?->hasMembershipExpired() ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state ? $state->format('M j, Y') : __('filament.resources.user.messages.never')),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('filament.resources.user.columns.verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('filament.resources.user.columns.last_login'))
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->placeholder(__('filament.resources.user.messages.never'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('filament.resources.user.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('filament.resources.user.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('membership_plan_id')
                    ->label(__('filament.resources.user.filters.membership_plan'))
                    ->relationship('membershipPlan', 'name')
                    ->preload(),

                Tables\Filters\TernaryFilter::make('email_verified_at')
                    ->label(__('filament.resources.user.filters.email_verified'))
                    ->nullable(),

                Tables\Filters\Filter::make('membership_expired')
                    ->label(__('filament.resources.user.filters.membership_expired'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('membership_expires_at')
                        ->where('membership_expires_at', '<', now())
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->label(__('filament.resources.user.filters.created_at'))
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
                    ->label(__('filament.resources.user.filters.api_tokens'))
                    ->options([
                        'with_tokens' => __('filament.resources.user.filter_options.has_tokens'),
                        'without_tokens' => __('filament.resources.user.filter_options.no_tokens'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with_tokens' => $query->has('tokens'),
                            'without_tokens' => $query->doesntHave('tokens'),
                            default => $query,
                        };
                    }),

                Tables\Filters\SelectFilter::make('login_activity')
                    ->label(__('filament.resources.user.filters.login_activity'))
                    ->options([
                        'recent' => __('filament.resources.user.filter_options.recent_login'),
                        'inactive' => __('filament.resources.user.filter_options.inactive_login'),
                        'never' => __('filament.resources.user.filter_options.never_logged_in'),
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
                    ->label(__('filament.resources.user.filters.has_active_tokens'))
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
                        ->label(__('filament.resources.user.actions.verify_email'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn (User $record) => $record->update(['email_verified_at' => now()]))
                        ->visible(fn (User $record) => ! $record->email_verified_at)
                        ->requiresConfirmation(),
                    Tables\Actions\Action::make('extend_membership')
                        ->label(__('filament.resources.user.actions.extend_membership'))
                        ->icon('heroicon-o-calendar-days')
                        ->color('warning')
                        ->form([
                            Forms\Components\DateTimePicker::make('new_expiry')
                                ->label(__('filament.resources.user.messages.new_expiry_date'))
                                ->required()
                                ->default(fn (User $record) => $record->membership_expires_at?->addMonth() ?? now()->addMonth()
                                ),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->update(['membership_expires_at' => $data['new_expiry']]);
                        })
                        ->visible(fn (User $record) => $record->membershipPlan),
                    Tables\Actions\Action::make('generate_token')
                        ->label(__('filament.resources.user.actions.generate_token'))
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
                                ->title(__('filament.resources.user.messages.token_generated_title'))
                                ->body(__('filament.resources.user.messages.token_generated_body', [
                                    'token' => $token->plainTextToken,
                                    'expires' => now()->addDays(30)->format('M j, Y g:i A'),
                                ]))
                                ->success()
                                ->duration(30000) // Show for 30 seconds
                                ->persistent() // Keep until manually dismissed
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.resources.user.messages.generate_token_heading'))
                        ->modalDescription(__('filament.resources.user.messages.generate_token_description'))
                        ->modalSubmitActionLabel(__('filament.resources.user.messages.generate_token_submit')),
                    Tables\Actions\Action::make('manage_tokens')
                        ->label(__('filament.resources.user.actions.manage_tokens'))
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
                                ->label(__('filament.common.actions.close'))
                                ->color('gray')
                                ->close(),
                        ])
                        ->modalWidth('4xl')
                        ->modalHeading(fn (User $record) => __('filament.resources.user.messages.manage_tokens_heading', ['name' => $record->name]))
                        ->visible(fn (User $record) => $record->tokens()->count() > 0),
                    Tables\Actions\Action::make('revoke_all_tokens')
                        ->label(__('filament.resources.user.actions.revoke_all_tokens'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (User $record) {
                            $tokenCount = $record->tokens()->count();
                            $record->tokens()->delete();

                            Notification::make()
                                ->title(__('filament.resources.user.messages.all_tokens_revoked_title'))
                                ->body(__('filament.resources.user.messages.all_tokens_revoked_body', [
                                    'count' => $tokenCount,
                                    'name' => $record->name,
                                ]))
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.resources.user.messages.revoke_all_heading'))
                        ->modalDescription(fn (User $record) => __('filament.resources.user.messages.revoke_all_description', ['name' => $record->name]))
                        ->modalSubmitActionLabel(__('filament.resources.user.messages.revoke_all_submit'))
                        ->visible(fn (User $record) => $record->tokens()->count() > 0),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('verify_emails')
                        ->label(__('filament.resources.user.actions.verify_emails'))
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['email_verified_at' => now()]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('assign_plan')
                        ->label(__('filament.resources.user.actions.assign_plan'))
                        ->icon('heroicon-o-credit-card')
                        ->color('info')
                        ->form([
                            Forms\Components\Select::make('membership_plan_id')
                                ->label(__('filament.resources.user.fields.membership_plan'))
                                ->options(MembershipPlan::active()->pluck('name', 'id'))
                                ->required(),
                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label(__('filament.resources.user.forms.expires_at'))
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
                        ->label(__('filament.resources.user.actions.bulk_generate_tokens'))
                        ->icon('heroicon-o-key')
                        ->color('info')
                        ->form([
                            Forms\Components\TextInput::make('token_name_prefix')
                                ->label(__('filament.resources.user.forms.token_name_prefix'))
                                ->required()
                                ->default(__('filament.resources.user.forms.token_name_prefix_default'))
                                ->helperText(__('filament.resources.user.forms.token_name_prefix_help')),
                            Forms\Components\Select::make('abilities')
                                ->label(__('filament.resources.user.forms.token_abilities'))
                                ->multiple()
                                ->options([
                                    '*' => __('filament.resources.user.abilities.all'),
                                    'auth:user' => __('filament.resources.user.abilities.user_profile'),
                                    'auth:logout' => __('filament.resources.user.abilities.logout'),
                                ])
                                ->default(['*'])
                                ->helperText(__('filament.resources.user.forms.token_abilities_help')),
                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label(__('filament.resources.user.forms.expires_at'))
                                ->nullable()
                                ->default(now()->addDays(30))
                                ->helperText(__('filament.resources.user.forms.expires_at_help')),
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
                                ->title(__('filament.resources.user.messages.bulk_token_generation_title'))
                                ->body(__('filament.resources.user.messages.bulk_token_generation_body', ['count' => $generatedCount]))
                                ->success()
                                ->send();
                        })
                        ->modalWidth('lg')
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.resources.user.forms.bulk_generate_heading'))
                        ->modalDescription(__('filament.resources.user.forms.bulk_generate_description'))
                        ->modalSubmitActionLabel(__('filament.resources.user.forms.bulk_generate_submit')),
                    Tables\Actions\BulkAction::make('bulk_revoke_tokens')
                        ->label(__('filament.resources.user.actions.bulk_revoke_tokens'))
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
                                ->title(__('filament.resources.user.messages.bulk_token_revocation_title'))
                                ->body(__('filament.resources.user.messages.bulk_token_revocation_body', [
                                    'count' => $totalRevoked,
                                    'users' => $records->count(),
                                ]))
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading(__('filament.resources.user.forms.bulk_revoke_heading'))
                        ->modalDescription(__('filament.resources.user.forms.bulk_revoke_description'))
                        ->modalSubmitActionLabel(__('filament.resources.user.forms.bulk_revoke_submit')),
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
