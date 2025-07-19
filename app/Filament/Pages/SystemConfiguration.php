<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Settings\AuthenticatedApiLimitsSettings;
use App\Settings\GeneralSettings;
use App\Settings\GuestApiLimitsSettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * System Configuration Page.
 *
 * This page provides a comprehensive settings management interface
 * with multiple tabs for different configuration areas.
 */
class SystemConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.system-configuration';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return 'System Configuration';
    }

    public function getTitle(): string
    {
        return 'System Configuration';
    }

    public function getHeading(): string
    {
        return 'System Configuration';
    }

    public function getSubheading(): ?string
    {
        return 'Manage system-wide settings and API limitations';
    }

    /**
     * Mount the page and load settings data.
     */
    public function mount(): void
    {
        $generalSettings = app(GeneralSettings::class);
        $guestSettings = app(GuestApiLimitsSettings::class);
        $authSettings = app(AuthenticatedApiLimitsSettings::class);

        $this->form->fill([
            // General settings
            'site_name' => $generalSettings->site_name,
            'site_description' => $generalSettings->site_description,
            'site_url' => $generalSettings->site_url,
            'admin_email' => $generalSettings->admin_email,
            'support_email' => $generalSettings->support_email,
            'allow_user_registration' => $generalSettings->allow_user_registration,
            'require_email_verification' => $generalSettings->require_email_verification,
            'default_timezone' => $generalSettings->default_timezone,
            'maintenance_mode' => $generalSettings->maintenance_mode,
            'maintenance_message' => $generalSettings->maintenance_message,
            'terms_of_service_url' => $generalSettings->terms_of_service_url,
            'privacy_policy_url' => $generalSettings->privacy_policy_url,

            // Guest API limits
            'guest_api_limits' => [
                'daily_request_limit' => $guestSettings->daily_request_limit,
                'hourly_request_limit' => $guestSettings->hourly_request_limit,
                'rate_limit_per_minute' => $guestSettings->rate_limit_per_minute,
                'allowed_platforms' => $guestSettings->allowed_platforms,
                'allowed_qualities' => $guestSettings->allowed_qualities,
                'allowed_formats' => $guestSettings->allowed_formats,
                'max_file_size_mb' => $guestSettings->max_file_size_mb,
            ],

            // Authenticated API limits
            'authenticated_api_limits' => [
                'daily_request_limit' => $authSettings->daily_request_limit,
                'hourly_request_limit' => $authSettings->hourly_request_limit,
                'rate_limit_per_minute' => $authSettings->rate_limit_per_minute,
                'allowed_platforms' => $authSettings->allowed_platforms,
                'allowed_qualities' => $authSettings->allowed_qualities,
                'allowed_formats' => $authSettings->allowed_formats,
                'max_file_size_mb' => $authSettings->max_file_size_mb,
            ],
        ]);
    }

    /**
     * Get header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('save')
                ->label('Save Settings')
                ->action('save')
                ->color('primary')
                ->icon('heroicon-o-check'),
        ];
    }

    /**
     * Save the settings.
     */
    public function save(): void
    {
        $data = $this->form->getState();

        // Save general settings
        $generalSettings = app(GeneralSettings::class);
        $generalSettings->site_name = $data['site_name'];
        $generalSettings->site_description = $data['site_description'];
        $generalSettings->site_url = $data['site_url'];
        $generalSettings->admin_email = $data['admin_email'];
        $generalSettings->support_email = $data['support_email'];
        $generalSettings->allow_user_registration = $data['allow_user_registration'];
        $generalSettings->require_email_verification = $data['require_email_verification'];
        $generalSettings->default_timezone = $data['default_timezone'];
        $generalSettings->maintenance_mode = $data['maintenance_mode'];
        $generalSettings->maintenance_message = $data['maintenance_message'];
        $generalSettings->terms_of_service_url = $data['terms_of_service_url'];
        $generalSettings->privacy_policy_url = $data['privacy_policy_url'];
        $generalSettings->save();

        // Save guest API limits
        $guestSettings = app(GuestApiLimitsSettings::class);
        foreach ($data['guest_api_limits'] as $key => $value) {
            $guestSettings->$key = $value;
        }
        $guestSettings->save();

        // Save authenticated API limits
        $authSettings = app(AuthenticatedApiLimitsSettings::class);
        foreach ($data['authenticated_api_limits'] as $key => $value) {
            $authSettings->$key = $value;
        }
        $authSettings->save();

        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();
    }

    /**
     * Get the form schema with tabs.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->tabs([
                        $this->getGeneralSettingsTab(),
                        $this->getGuestApiLimitsTab(),
                        $this->getAuthenticatedApiLimitsTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Get the General Settings tab.
     */
    private function getGeneralSettingsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('General Settings')
            ->icon('heroicon-o-cog-6-tooth')
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

                Forms\Components\Section::make('User Management')
                    ->description('User registration and verification settings')
                    ->schema([
                        Forms\Components\Toggle::make('allow_user_registration')
                            ->label('Allow User Registration')
                            ->helperText('Enable or disable new user registrations'),

                        Forms\Components\Toggle::make('require_email_verification')
                            ->label('Require Email Verification')
                            ->helperText('Require users to verify their email address'),

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

    /**
     * Get the Guest API Limits tab.
     */
    private function getGuestApiLimitsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Guest User API Limits')
            ->icon('heroicon-o-user')
            ->schema([
                Forms\Components\Section::make('Request Limits')
                    ->description('API request limitations for unauthenticated users')
                    ->schema([
                        Forms\Components\TextInput::make('guest_api_limits.daily_request_limit')
                            ->label('Daily Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->suffix('requests per day')
                            ->helperText('Maximum number of API calls per day for guest users'),

                        Forms\Components\TextInput::make('guest_api_limits.hourly_request_limit')
                            ->label('Hourly Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('requests per hour')
                            ->helperText('Maximum number of API calls per hour for guest users'),

                        Forms\Components\TextInput::make('guest_api_limits.rate_limit_per_minute')
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
                        Forms\Components\CheckboxList::make('guest_api_limits.allowed_platforms')
                            ->label('Allowed Platforms')
                            ->options(GuestApiLimitsSettings::getPlatformOptions())
                            ->required()
                            ->helperText('Select which platforms guest users can access'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Quality & Format Restrictions')
                    ->description('Available quality and format options for guest users')
                    ->schema([
                        Forms\Components\CheckboxList::make('guest_api_limits.allowed_qualities')
                            ->label('Allowed Qualities')
                            ->options(GuestApiLimitsSettings::getQualityOptions())
                            ->required()
                            ->helperText('Select which quality options guest users can request'),

                        Forms\Components\CheckboxList::make('guest_api_limits.allowed_formats')
                            ->label('Allowed Formats')
                            ->options(GuestApiLimitsSettings::getFormatOptions())
                            ->required()
                            ->helperText('Select which file formats guest users can download'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('File & Download Limits')
                    ->description('File size and download restrictions')
                    ->schema([
                        Forms\Components\TextInput::make('guest_api_limits.max_file_size_mb')
                            ->label('Maximum File Size')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->suffix('MB')
                            ->helperText('Maximum file size that guest users can download'),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Get the Authenticated API Limits tab.
     */
    private function getAuthenticatedApiLimitsTab(): Forms\Components\Tabs\Tab
    {
        return Forms\Components\Tabs\Tab::make('Authenticated User API Limits (No Package)')
            ->icon('heroicon-o-user')
            ->schema([
                Forms\Components\Section::make('Request Limits')
                    ->description('API request limitations for authenticated users without subscription packages')
                    ->schema([
                        Forms\Components\TextInput::make('authenticated_api_limits.daily_request_limit')
                            ->label('Daily Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10000)
                            ->suffix('requests per day')
                            ->helperText('Maximum number of API calls per day for authenticated users'),

                        Forms\Components\TextInput::make('authenticated_api_limits.hourly_request_limit')
                            ->label('Hourly Request Limit')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->suffix('requests per hour')
                            ->helperText('Maximum number of API calls per hour for authenticated users'),

                        Forms\Components\TextInput::make('authenticated_api_limits.rate_limit_per_minute')
                            ->label('Rate Limit Per Minute')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('requests per minute')
                            ->helperText('Rate limiting for authenticated users'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Platform Access')
                    ->description('Allowed platforms for authenticated users without packages')
                    ->schema([
                        Forms\Components\CheckboxList::make('authenticated_api_limits.allowed_platforms')
                            ->label('Allowed Platforms')
                            ->options(AuthenticatedApiLimitsSettings::getPlatformOptions())
                            ->required()
                            ->helperText('Select which platforms authenticated users can access'),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('Quality & Format Restrictions')
                    ->description('Available quality and format options for authenticated users')
                    ->schema([
                        Forms\Components\CheckboxList::make('authenticated_api_limits.allowed_qualities')
                            ->label('Allowed Qualities')
                            ->options(AuthenticatedApiLimitsSettings::getQualityOptions())
                            ->required()
                            ->helperText('Select which quality options authenticated users can request'),

                        Forms\Components\CheckboxList::make('authenticated_api_limits.allowed_formats')
                            ->label('Allowed Formats')
                            ->options(AuthenticatedApiLimitsSettings::getFormatOptions())
                            ->required()
                            ->helperText('Select which file formats authenticated users can download'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('File & Download Limits')
                    ->description('File size and download restrictions')
                    ->schema([
                        Forms\Components\TextInput::make('authenticated_api_limits.max_file_size_mb')
                            ->label('Maximum File Size')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5000)
                            ->suffix('MB')
                            ->helperText('Maximum file size that authenticated users can download'),
                    ])
                    ->columns(2),
            ]);
    }
}
