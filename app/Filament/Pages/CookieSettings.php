<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\CookieSettings as CookieSettingsClass;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

/**
 * Cookie Settings Page.
 *
 * This page provides management interface for cookie file configuration
 * used for authenticated video extraction from platforms requiring login.
 */
class CookieSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static string $settings = CookieSettingsClass::class;

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return 'Cookie Settings';
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return 'Cookie Settings';
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return 'Cookie Settings';
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): ?string
    {
        return 'Manage cookie configuration files for authenticated video extraction';
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Cookie File Configuration')
                    ->description('Upload cookie file for authenticated video extraction from platforms requiring login')
                    ->schema([
                        Forms\Components\FileUpload::make('cookie_file_path')
                            ->disk('local')
                            ->label('Cookie File')
                            ->directory('cookies')
                            ->maxSize(10240) // 10MB max
                            ->helperText('Upload a .txt file containing cookies for authenticated video extraction. This enables downloading from platforms that require login credentials.')
                            ->placeholder('No cookie file uploaded')
                            ->downloadable()
                            ->openable()
                            ->deletable(),
                    ])
                    ->columns(1),
            ]);
    }
}
