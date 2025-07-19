<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipPlanResource\Pages;
use App\Models\MembershipPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Membership Plan Resource for Filament Admin Panel.
 * 
 * Manages membership plans with pricing, limits, and features.
 */
class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Membership Plans';
    }

    public static function getModelLabel(): string
    {
        return 'Membership Plan';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Membership Plans';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) => 
                                $context === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Pricing')
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01),

                        Forms\Components\Select::make('currency')
                            ->required()
                            ->options([
                                'USD' => 'USD ($)',
                                'EUR' => 'EUR (€)',
                                'GBP' => 'GBP (£)',
                            ])
                            ->default('USD'),

                        Forms\Components\Select::make('billing_cycle')
                            ->required()
                            ->options(MembershipPlan::getAvailableBillingCycles())
                            ->default('monthly'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Request Limits')
                    ->description('Set to 0 for unlimited requests')
                    ->schema([
                        Forms\Components\TextInput::make('daily_request_limit')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('requests/day'),

                        Forms\Components\TextInput::make('weekly_request_limit')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('requests/week'),

                        Forms\Components\TextInput::make('monthly_request_limit')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('requests/month'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Platform & Quality Restrictions')
                    ->description('Leave empty to allow all options')
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_platforms')
                            ->options(MembershipPlan::getAvailablePlatforms())
                            ->columns(2),

                        Forms\Components\CheckboxList::make('allowed_qualities')
                            ->options(MembershipPlan::getAvailableQualities())
                            ->columns(4),

                        Forms\Components\CheckboxList::make('allowed_formats')
                            ->options(MembershipPlan::getAvailableFormats())
                            ->columns(3),
                    ]),

                Forms\Components\Section::make('Features & Limits')
                    ->schema([
                        Forms\Components\Toggle::make('priority_processing')
                            ->label('Priority Processing')
                            ->helperText('Process requests with higher priority'),

                        Forms\Components\Toggle::make('bulk_downloads')
                            ->label('Bulk Downloads')
                            ->helperText('Allow multiple downloads at once'),

                        Forms\Components\Toggle::make('api_access')
                            ->label('API Access')
                            ->helperText('Allow access to API endpoints'),

                        Forms\Components\TextInput::make('concurrent_downloads')
                            ->required()
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(50)
                            ->suffix('concurrent downloads'),

                        Forms\Components\TextInput::make('max_file_size_mb')
                            ->required()
                            ->numeric()
                            ->default(100)
                            ->minValue(1)
                            ->suffix('MB'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Plan Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active plans are available for selection'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Featured plans are highlighted to users'),

                        Forms\Components\TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'monthly' => 'info',
                        'yearly' => 'success',
                        'lifetime' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('daily_request_limit')
                    ->label('Daily Limit')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : number_format($state))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('monthly_request_limit')
                    ->label('Monthly Limit')
                    ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : number_format($state))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Plans'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured Plans'),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->options(MembershipPlan::getAvailableBillingCycles()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembershipPlans::route('/'),
            'create' => Pages\CreateMembershipPlan::route('/create'),
            'view' => Pages\ViewMembershipPlan::route('/{record}'),
            'edit' => Pages\EditMembershipPlan::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('users');
    }
}
