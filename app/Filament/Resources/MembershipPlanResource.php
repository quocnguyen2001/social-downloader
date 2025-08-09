<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MembershipPlanResource\Pages;
use App\Models\MembershipPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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
                            ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) => $context === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null
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

                        Forms\Components\TextInput::make('total_request_download')
                            ->label('Total Download Requests')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('total requests')
                            ->helperText('Total number of download requests allowed (0 = unlimited)'),
                    ])
                    ->columns(2),

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
                    ->columns(2),
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

                Tables\Columns\TextColumn::make('total_request_download')
                    ->label('Total Downloads')
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Basic Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Plan Name')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->placeholder('No description provided'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Pricing & Billing')
                    ->schema([
                        Infolists\Components\TextEntry::make('price')
                            ->label('Price')
                            ->money('USD')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('currency')
                            ->label('Currency')
                            ->badge(),

                        Infolists\Components\TextEntry::make('billing_cycle')
                            ->label('Billing Cycle')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'monthly' => 'info',
                                'yearly' => 'success',
                                'lifetime' => 'warning',
                                default => 'gray',
                            }),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Request Limits')
                    ->schema([
                        Infolists\Components\TextEntry::make('daily_request_limit')
                            ->label('Daily Requests')
                            ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : number_format($state))
                            ->badge()
                            ->color(fn ($state) => $state === 0 ? 'success' : 'info'),

                        Infolists\Components\TextEntry::make('total_request_download')
                            ->label('Total Download Requests')
                            ->formatStateUsing(fn ($state) => $state === 0 ? 'Unlimited' : number_format($state))
                            ->badge()
                            ->color(fn ($state) => $state === 0 ? 'success' : 'warning'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Platform & Quality Restrictions')
                    ->schema([
                        Infolists\Components\TextEntry::make('allowed_platforms')
                            ->label('Allowed Platforms')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All platforms allowed'),

                        Infolists\Components\TextEntry::make('allowed_qualities')
                            ->label('Allowed Qualities')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All qualities allowed'),

                        Infolists\Components\TextEntry::make('allowed_formats')
                            ->label('Allowed Formats')
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('All formats allowed'),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make('Features & Settings')
                    ->schema([
                        Infolists\Components\IconEntry::make('priority_processing')
                            ->label('Priority Processing')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\IconEntry::make('is_featured')
                            ->label('Featured')
                            ->boolean()
                            ->trueIcon('heroicon-o-star')
                            ->falseIcon('heroicon-o-star')
                            ->trueColor('warning')
                            ->falseColor('gray'),

                        Infolists\Components\TextEntry::make('sort_order')
                            ->label('Sort Order')
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('users_count')
                            ->label('Active Users')
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
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
