<?php

namespace App\Filament\Resources;

use App\Filament\Components\SharedColors;
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

class MembershipPlanResource extends Resource
{
    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.user_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.membership_plans');
    }

    public static function getModelLabel(): string
    {
        return __('filament.resources.membership_plan.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.resources.membership_plan.plural_label');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.basic_information'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('filament.resources.membership_plan.fields.name'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $context, $state, Forms\Set $set) => $context === 'create' ? $set('slug', \Illuminate\Support\Str::slug($state)) : null
                            ),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('filament.resources.membership_plan.fields.slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash']),

                        Forms\Components\Textarea::make('description')
                            ->label(__('filament.resources.membership_plan.fields.description'))
                            ->maxLength(1000)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.pricing'))
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label(__('filament.resources.membership_plan.fields.price'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('$')
                            ->step(0.01),

                        Forms\Components\Select::make('currency')
                            ->label(__('filament.resources.membership_plan.fields.currency'))
                            ->required()
                            ->options([
                                'VND' => 'VND (₫)',
                                'USD' => 'USD ($)',
                                'EUR' => 'EUR (€)',
                                'GBP' => 'GBP (£)',
                            ])
                            ->default('USD'),

                        Forms\Components\Select::make('billing_cycle')
                            ->label(__('filament.resources.membership_plan.fields.billing_cycle'))
                            ->required()
                            ->options(MembershipPlan::getAvailableBillingCycles())
                            ->default('monthly'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.request_limits'))
                    ->description(__('filament.resources.membership_plan.descriptions.request_limits'))
                    ->schema([
                        Forms\Components\TextInput::make('daily_request_limit')
                            ->label(__('filament.resources.membership_plan.fields.daily_request_limit'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('requests/day'),

                        Forms\Components\TextInput::make('total_request_download')
                            ->label(__('filament.resources.membership_plan.fields.total_request_download'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('total requests')
                            ->helperText(__('filament.resources.membership_plan.descriptions.total_download_requests')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.platform_quality_restrictions'))
                    ->description(__('filament.resources.membership_plan.descriptions.platform_quality_restrictions'))
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_platforms')
                            ->label(__('filament.resources.membership_plan.fields.allowed_platforms'))
                            ->options(MembershipPlan::getAvailablePlatforms())
                            ->columns(2),

                        Forms\Components\CheckboxList::make('allowed_qualities')
                            ->label(__('filament.resources.membership_plan.fields.allowed_qualities'))
                            ->options(MembershipPlan::getAvailableQualities())
                            ->columns(4),
                    ]),

                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.features_limits'))
                    ->schema([
                        Forms\Components\Toggle::make('priority_processing')
                            ->label(__('filament.resources.membership_plan.fields.priority_processing'))
                            ->helperText(__('filament.resources.membership_plan.descriptions.priority_processing')),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('filament.resources.membership_plan.sections.plan_settings'))
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('filament.resources.membership_plan.fields.is_active'))
                            ->default(true)
                            ->helperText(__('filament.resources.membership_plan.descriptions.active')),

                        Forms\Components\Toggle::make('is_featured')
                            ->label(__('filament.resources.membership_plan.fields.is_featured'))
                            ->helperText(__('filament.resources.membership_plan.descriptions.featured')),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('filament.resources.membership_plan.fields.sort_order'))
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->helperText(__('filament.resources.membership_plan.descriptions.sort_order')),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament.resources.membership_plan.columns.name'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('filament.resources.membership_plan.columns.price'))
                    ->money('USD')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('billing_cycle')
                    ->label(__('filament.resources.membership_plan.columns.billing_cycle'))
                    ->badge()
                    ->color(fn (string $state): string => SharedColors::membershipPlanBillingCycle()[$state] ?? 'gray'),

                Tables\Columns\TextColumn::make('daily_request_limit')
                    ->label(__('filament.resources.membership_plan.columns.daily_limit'))
                    ->formatStateUsing(fn ($state) => $state === 0 ? __('filament.resources.membership_plan.messages.unlimited') : number_format($state))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('total_request_download')
                    ->label(__('filament.resources.membership_plan.columns.total_downloads'))
                    ->formatStateUsing(fn ($state) => $state === 0 ? __('filament.resources.membership_plan.messages.unlimited') : number_format($state))
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label(__('filament.resources.membership_plan.columns.users'))
                    ->counts('users')
                    ->alignEnd(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('filament.resources.membership_plan.columns.active'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('filament.resources.membership_plan.columns.featured'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('filament.resources.membership_plan.columns.order'))
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('filament.resources.membership_plan.filters.active_plans')),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label(__('filament.resources.membership_plan.filters.featured_plans')),
                Tables\Filters\SelectFilter::make('billing_cycle')
                    ->label(__('filament.resources.membership_plan.filters.billing_cycle'))
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
                        ->label(__('filament.resources.membership_plan.actions.activate_selected'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label(__('filament.resources.membership_plan.actions.deactivate_selected'))
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
                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.basic_information'))
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label(__('filament.resources.membership_plan.fields.plan_name'))
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('slug')
                            ->label(__('filament.resources.membership_plan.fields.slug'))
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('description')
                            ->label(__('filament.resources.membership_plan.fields.description'))
                            ->placeholder(__('filament.resources.membership_plan.messages.no_description_provided')),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.pricing_billing'))
                    ->schema([
                        Infolists\Components\TextEntry::make('price')
                            ->label(__('filament.resources.membership_plan.fields.price'))
                            ->money('USD')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight('bold')
                            ->color('success'),

                        Infolists\Components\TextEntry::make('currency')
                            ->label(__('filament.resources.membership_plan.fields.currency'))
                            ->badge(),

                        Infolists\Components\TextEntry::make('billing_cycle')
                            ->label(__('filament.resources.membership_plan.fields.billing_cycle'))
                            ->badge()
                            ->color(fn (string $state): string => SharedColors::membershipPlanBillingCycle()[$state] ?? 'gray'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.request_limits'))
                    ->schema([
                        Infolists\Components\TextEntry::make('daily_request_limit')
                            ->label(__('filament.resources.membership_plan.fields.daily_request_limit'))
                            ->formatStateUsing(fn ($state) => $state === 0 ? __('filament.resources.membership_plan.messages.unlimited') : number_format($state))
                            ->badge()
                            ->color(fn ($state) => $state === 0 ? 'success' : 'info'),

                        Infolists\Components\TextEntry::make('total_request_download')
                            ->label(__('filament.resources.membership_plan.fields.total_request_download'))
                            ->formatStateUsing(fn ($state) => $state === 0 ? __('filament.resources.membership_plan.messages.unlimited') : number_format($state))
                            ->badge()
                            ->color(fn ($state) => $state === 0 ? 'success' : 'warning'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.platform_quality_restrictions'))
                    ->schema([
                        Infolists\Components\TextEntry::make('allowed_platforms')
                            ->label(__('filament.resources.membership_plan.fields.allowed_platforms'))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder(__('filament.resources.membership_plan.messages.all_platforms_allowed')),

                        Infolists\Components\TextEntry::make('allowed_qualities')
                            ->label(__('filament.resources.membership_plan.fields.allowed_qualities'))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder(__('filament.resources.membership_plan.messages.all_qualities_allowed')),

                        Infolists\Components\TextEntry::make('allowed_formats')
                            ->label(__('filament.resources.membership_plan.fields.allowed_formats'))
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder(__('filament.resources.membership_plan.messages.all_formats_allowed')),
                    ])
                    ->columns(1),

                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.features_limits'))
                    ->schema([
                        Infolists\Components\IconEntry::make('priority_processing')
                            ->label(__('filament.resources.membership_plan.fields.priority_processing'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\IconEntry::make('is_active')
                            ->label(__('filament.resources.membership_plan.fields.is_active'))
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        Infolists\Components\IconEntry::make('is_featured')
                            ->label(__('filament.resources.membership_plan.fields.is_featured'))
                            ->boolean()
                            ->trueIcon('heroicon-o-star')
                            ->falseIcon('heroicon-o-star')
                            ->trueColor('warning')
                            ->falseColor('gray'),

                        Infolists\Components\TextEntry::make('sort_order')
                            ->label(__('filament.resources.membership_plan.fields.sort_order'))
                            ->badge()
                            ->color('gray'),

                        Infolists\Components\TextEntry::make('users_count')
                            ->label(__('filament.resources.membership_plan.fields.active_users'))
                            ->badge()
                            ->color('primary'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make(__('filament.resources.membership_plan.sections.timestamps'))
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label(__('filament.common.fields.created_at'))
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('updated_at')
                            ->label(__('filament.common.fields.updated_at'))
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
