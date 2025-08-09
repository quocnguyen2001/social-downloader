<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GuestApiLimitsSettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

/**
 * Guest User API Limits Settings Page.
 *
 * This page provides management interface for API restrictions and limitations
 * for unauthenticated (guest) users.
 */
class GuestUserApiLimitsSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static string $settings = GuestApiLimitsSettings::class;

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return 'Guest User API Limits';
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return 'Guest User API Limits';
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return 'Guest User API Limits';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): ?string
    {
        return 'Manage API restrictions and limitations for unauthenticated users';
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Request Limits')
                    ->description('API request limitations for unauthenticated users')
                    ->schema([
                        Forms\Components\TextInput::make('daily_request_limit')
                            ->label('Daily Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->suffix('requests per day')
                            ->helperText('Maximum number of API calls per day for guest users'),

                        Forms\Components\TextInput::make('hourly_request_limit')
                            ->label('Hourly Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('requests per hour')
                            ->helperText('Maximum number of API calls per hour for guest users'),

                        Forms\Components\TextInput::make('rate_limit_per_minute')
                            ->label('Rate Limit Per Minute')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('requests per minute')
                            ->helperText('Rate limiting for guest users'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Platform Access')
                    ->description('Allowed platforms for guest users')
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_platforms')
                            ->label('Allowed Platforms')
                            ->options(GuestApiLimitsSettings::getPlatformOptions())
                            ->required()
                            ->helperText('Select which platforms guest users can access'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Quality & Format Restrictions')
                    ->description('Available quality and format options for guest users')
                    ->schema([
                        Forms\Components\CheckboxList::make('allowed_qualities')
                            ->label('Allowed Qualities')
                            ->options(GuestApiLimitsSettings::getQualityOptions())
                            ->required()
                            ->helperText('Select which quality options guest users can request'),

                        Forms\Components\CheckboxList::make('allowed_formats')
                            ->label('Allowed Formats')
                            ->options(GuestApiLimitsSettings::getFormatOptions())
                            ->required()
                            ->helperText('Select which file formats guest users can download'),
                    ])
                    ->columns(2),
            ]);
    }
}
