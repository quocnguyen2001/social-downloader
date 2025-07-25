<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\GeneralSettings as GeneralSettingsClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

/**
 * General Settings Page.
 *
 * This page provides management interface for general application settings
 * including site information, contact details, user management, and maintenance mode.
 */
class GeneralSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string $settings = GeneralSettingsClass::class;

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return 'General Settings';
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return 'General Settings';
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return 'General Settings';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): ?string
    {
        return 'Manage basic website information and configuration settings';
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Site Information')
                    ->description('Basic website information and configuration')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('Site Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Social Downloader'),

                        Forms\Components\Textarea::make('site_description')
                            ->label('Site Description')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('Download videos and media from social platforms'),

                        Forms\Components\TextInput::make('site_url')
                            ->label('Site URL')
                            ->required()
                            ->url()
                            ->placeholder('https://example.com'),
                        Forms\Components\Select::make('default_timezone')
                            ->label('Default Timezone')
                            ->options([
                                'UTC' => 'UTC',
                                'America/New_York' => 'America/New_York',
                                'America/Chicago' => 'America/Chicago',
                                'America/Denver' => 'America/Denver',
                                'America/Los_Angeles' => 'America/Los_Angeles',
                                'Europe/London' => 'Europe/London',
                                'Europe/Paris' => 'Europe/Paris',
                                'Asia/Tokyo' => 'Asia/Tokyo',
                                'Asia/Shanghai' => 'Asia/Shanghai',
                                'Asia/Ho_Chi_Minh' => 'Asia/Ho_Chi_Minh',
                            ])
                            ->required()
                            ->searchable(),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Contact Information')
                    ->description('Administrative and support contact details')
                    ->schema([
                        Forms\Components\TextInput::make('admin_email')
                            ->label('Admin Email')
                            ->required()
                            ->email()
                            ->placeholder('admin@example.com'),

                        Forms\Components\TextInput::make('support_email')
                            ->label('Support Email')
                            ->required()
                            ->email()
                            ->placeholder('support@example.com'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Maintenance Mode')
                    ->description('System maintenance settings')
                    ->schema([
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Enable Maintenance Mode')
                            ->helperText('Put the site in maintenance mode'),

                        Forms\Components\Textarea::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('We are currently performing maintenance. Please check back later.'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('SEO Settings')
                    ->description('Search engine optimization settings')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(60)
                            ->placeholder('Social Downloader - Download Videos from Social Platforms')
                            ->helperText('Recommended length: 50-60 characters'),

                        Forms\Components\Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->maxLength(160)
                            ->rows(3)
                            ->placeholder('Download videos and media from popular social platforms...')
                            ->helperText('Recommended length: 150-160 characters'),

                        Forms\Components\Textarea::make('meta_keywords')
                            ->label('Meta Keywords')
                            ->maxLength(255)
                            ->rows(2)
                            ->placeholder('video downloader, social media downloader, youtube downloader')
                            ->helperText('Comma-separated keywords'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Copyright Information')
                    ->description('Website copyright details')
                    ->schema([
                        Forms\Components\TextInput::make('copyright_text')
                            ->label('Copyright Text')
                            ->maxLength(255)
                            ->placeholder('All rights reserved.'),

                        Forms\Components\TextInput::make('copyright_year')
                            ->label('Copyright Year')
                            ->maxLength(4)
                            ->placeholder((string) date('Y'))
                            ->helperText('Current year will be used if empty'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Brand Assets')
                    ->description('Logo and favicon management')
                    ->schema([
                        Forms\Components\FileUpload::make('logo_path')
                            ->label('Site Logo')
                            ->image()
                            ->directory('logos')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/svg+xml'])
                            ->maxSize(2048)
                            ->helperText('Upload your site logo (JPEG, PNG, SVG - Max 2MB)'),

                        Forms\Components\FileUpload::make('favicon_path')
                            ->label('Favicon')
                            ->image()
                            ->directory('favicons')
                            ->acceptedFileTypes(['image/x-icon', 'image/png'])
                            ->maxSize(512)
                            ->helperText('Upload favicon (ICO, PNG - Max 512KB)'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Legal Pages')
                    ->description('Links to legal documents')
                    ->schema([
                        Forms\Components\TextInput::make('terms_of_service_url')
                            ->label('Terms of Service URL')
                            ->url()
                            ->placeholder('https://example.com/terms'),

                        Forms\Components\TextInput::make('privacy_policy_url')
                            ->label('Privacy Policy URL')
                            ->url()
                            ->placeholder('https://example.com/privacy'),
                    ])
                    ->columns(2),
            ]);
    }
}
