<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\AuthenticatedApiLimitsSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class AuthenticatedUserApiLimitsSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('filament.navigation.groups.settings');
    }

    protected static string $settings = AuthenticatedApiLimitsSettings::class;

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return __('filament.navigation.labels.authenticated_user_api_limits');
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return __('filament.pages.api_limits_settings.authenticated_user.title');
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return __('filament.pages.api_limits_settings.authenticated_user.heading');
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): ?string
    {
        return __('filament.pages.api_limits_settings.authenticated_user.subheading');
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('filament.pages.api_limits_settings.sections.request_limits'))
                    ->description(__('filament.pages.api_limits_settings.descriptions.authenticated_request_limits'))
                    ->schema([
                        Forms\Components\TextInput::make('daily_request_limit')
                            ->label(__('filament.pages.api_limits_settings.fields.daily_request_limit'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10000)
                            ->suffix(__('filament.pages.api_limits_settings.suffixes.requests_per_day'))
                            ->helperText(__('filament.pages.api_limits_settings.help.daily_request_limit_authenticated')),

                        Forms\Components\TextInput::make('hourly_request_limit')
                            ->label(__('filament.pages.api_limits_settings.fields.hourly_request_limit'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->suffix(__('filament.pages.api_limits_settings.suffixes.requests_per_hour'))
                            ->helperText(__('filament.pages.api_limits_settings.help.hourly_request_limit_authenticated')),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label(__('filament.pages.api_limits_settings.fields.rate_limit_per_minute'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix(__('filament.pages.api_limits_settings.suffixes.requests_per_minute'))
                            ->helperText(__('filament.pages.api_limits_settings.help.rate_limit_authenticated')),
                    ])
                    ->columns(3),

                Forms\Components\Section::make(__('filament.pages.api_limits_settings.sections.platform_access'))
                    ->description(__('filament.pages.api_limits_settings.descriptions.authenticated_platform_access'))
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_platforms')
                            ->label(__('filament.pages.api_limits_settings.fields.allowed_platforms'))
                            ->options(AuthenticatedApiLimitsSettings::getPlatformOptions())
                            ->required()
                            ->helperText(__('filament.pages.api_limits_settings.help.allowed_platforms_authenticated')),
                    ])
                    ->columns(1),

                Forms\Components\Section::make(__('filament.pages.api_limits_settings.sections.quality_format_restrictions'))
                    ->description(__('filament.pages.api_limits_settings.descriptions.authenticated_quality_format'))
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_qualities')
                            ->label(__('filament.pages.api_limits_settings.fields.allowed_qualities'))
                            ->options(AuthenticatedApiLimitsSettings::getQualityOptions())
                            ->required()
                            ->helperText(__('filament.pages.api_limits_settings.help.allowed_qualities_authenticated')),

                        Forms\Components\CheckboxList::make('allowed_formats')
                            ->label(__('filament.pages.api_limits_settings.fields.allowed_formats'))
                            ->options(AuthenticatedApiLimitsSettings::getFormatOptions())
                            ->required()
                            ->helperText(__('filament.pages.api_limits_settings.help.allowed_formats_authenticated')),
                    ])
                    ->columns(2),
            ]);
    }
}
