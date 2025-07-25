<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\PayPalService;
use App\Services\VietQRService;
use App\Settings\PaymentGatewaySettings;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Illuminate\Support\Facades\Log;

/**
 * Payment Gateway Configuration Page.
 *
 * This page provides management interface for payment gateway settings
 * including bank transfer and PayPal configurations with tabbed interface.
 */
class PaymentGatewayConfiguration extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static string $settings = PaymentGatewaySettings::class;

    /**
     * Get VietQR service instance.
     */
    private function getVietQRService(): VietQRService
    {
        return app(VietQRService::class);
    }

    /**
     * Get PayPal service instance.
     */
    private function getPayPalService(): PayPalService
    {
        return new PayPalService(app(PaymentGatewaySettings::class));
    }

    /**
     * Get the navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return trans('payment_gateway.title');
    }

    /**
     * Get the page title.
     */
    public function getTitle(): string
    {
        return trans('payment_gateway.title');
    }

    /**
     * Get the page heading.
     */
    public function getHeading(): string
    {
        return trans('payment_gateway.title');
    }

    /**
     * Get the page subheading.
     */
    public function getSubheading(): ?string
    {
        return trans('payment_gateway.description');
    }

    /**
     * Get the form schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('payment_gateway_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make(trans('payment_gateway.tabs.bank_transfer'))
                            ->icon('heroicon-o-building-library')
                            ->schema($this->getBankTransferSchema()),

                        Forms\Components\Tabs\Tab::make(trans('payment_gateway.tabs.paypal'))
                            ->icon('heroicon-o-currency-dollar')
                            ->schema($this->getPayPalSchema()),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Get bank transfer configuration schema.
     */
    private function getBankTransferSchema(): array
    {
        return [
            Forms\Components\Section::make(trans('payment_gateway.sections.bank_transfer_config'))
                ->description(trans('payment_gateway.sections.bank_transfer_config_description'))
                ->schema([
                    Forms\Components\Select::make('bank_name')
                        ->label(trans('payment_gateway.fields.bank_name'))
                        ->options(fn (): array => $this->getBankOptions())
                        ->searchable()
                        ->placeholder(trans('payment_gateway.placeholders.bank_name'))
                        ->helperText(trans('payment_gateway.help.bank_name'))
                        ->suffixAction(
                            Forms\Components\Actions\Action::make('refresh_banks')
                                ->icon('heroicon-m-arrow-path')
                                ->tooltip(trans('payment_gateway.actions.refresh_banks'))
                                ->action(function () {
                                    try {
                                        $this->getVietQRService()->refreshCache();
                                        Notification::make()
                                            ->title(trans('payment_gateway.messages.success.bank_list_refreshed'))
                                            ->success()
                                            ->send();
                                    } catch (\Exception $e) {
                                        Notification::make()
                                            ->title(trans('payment_gateway.messages.error.bank_list_refresh_failed'))
                                            ->body($e->getMessage())
                                            ->danger()
                                            ->send();
                                    }
                                })
                        ),

                    Forms\Components\TextInput::make('bank_account')
                        ->label(trans('payment_gateway.fields.bank_account'))
                        ->placeholder(trans('payment_gateway.placeholders.bank_account'))
                        ->helperText(trans('payment_gateway.help.bank_account'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('bank_account_number')
                        ->label(trans('payment_gateway.fields.bank_account_number'))
                        ->placeholder(trans('payment_gateway.placeholders.bank_account_number'))
                        ->helperText(trans('payment_gateway.help.bank_account_number'))
                        ->maxLength(50),

                    Forms\Components\Textarea::make('money_transfer_content_template')
                        ->label(trans('payment_gateway.fields.money_transfer_content_template'))
                        ->placeholder(trans('payment_gateway.placeholders.money_transfer_content_template'))
                        ->helperText(trans('payment_gateway.help.money_transfer_content_template'))
                        ->rows(3)
                        ->maxLength(500),

                    Forms\Components\TextInput::make('api_transactions_api')
                        ->label(trans('payment_gateway.fields.api_transactions_api'))
                        ->placeholder(trans('payment_gateway.placeholders.api_transactions_api'))
                        ->helperText(trans('payment_gateway.help.api_transactions_api'))
                        ->url()
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make(trans('payment_gateway.sections.general_settings'))
                ->description(trans('payment_gateway.sections.general_settings_description'))
                ->schema([
                    Forms\Components\Toggle::make('bank_transfer_enabled')
                        ->label(trans('payment_gateway.fields.bank_transfer_enabled'))
                        ->helperText(trans('payment_gateway.help.bank_transfer_enabled'))
                        ->default(false),

                    Forms\Components\Select::make('bank_transfer_currency')
                        ->label(trans('payment_gateway.fields.bank_transfer_currency'))
                        ->options(PaymentGatewaySettings::getBankTransferCurrencyOptions())
                        ->helperText(trans('payment_gateway.help.bank_transfer_currency'))
                        ->default('VND'),
                ])
                ->columns(2),
        ];
    }

    /**
     * Get PayPal configuration schema.
     */
    private function getPayPalSchema(): array
    {
        return [
            Forms\Components\Section::make(trans('payment_gateway.sections.paypal_config'))
                ->description(trans('payment_gateway.sections.paypal_config_description'))
                ->schema([
                    Forms\Components\TextInput::make('paypal_client_id')
                        ->label(trans('payment_gateway.fields.paypal_client_id'))
                        ->placeholder(trans('payment_gateway.placeholders.paypal_client_id'))
                        ->helperText(trans('payment_gateway.help.paypal_client_id'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('paypal_client_secret')
                        ->label(trans('payment_gateway.fields.paypal_client_secret'))
                        ->placeholder(trans('payment_gateway.placeholders.paypal_client_secret'))
                        ->helperText(trans('payment_gateway.help.paypal_client_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),

                    Forms\Components\Select::make('paypal_environment')
                        ->label(trans('payment_gateway.fields.paypal_environment'))
                        ->options(PaymentGatewaySettings::getPayPalEnvironmentOptions())
                        ->helperText(trans('payment_gateway.help.paypal_environment'))
                        ->default('sandbox'),

                    Forms\Components\Select::make('paypal_currency')
                        ->label(trans('payment_gateway.fields.paypal_currency'))
                        ->options(PaymentGatewaySettings::getPayPalCurrencyOptions())
                        ->helperText(trans('payment_gateway.help.paypal_currency'))
                        ->default('USD'),

                    Forms\Components\TextInput::make('paypal_webhook_id')
                        ->label(trans('payment_gateway.fields.paypal_webhook_id'))
                        ->placeholder(trans('payment_gateway.placeholders.paypal_webhook_id'))
                        ->helperText(trans('payment_gateway.help.paypal_webhook_id'))
                        ->maxLength(255),

                    Forms\Components\TextInput::make('paypal_webhook_secret')
                        ->label(trans('payment_gateway.fields.paypal_webhook_secret'))
                        ->placeholder(trans('payment_gateway.placeholders.paypal_webhook_secret'))
                        ->helperText(trans('payment_gateway.help.paypal_webhook_secret'))
                        ->password()
                        ->revealable()
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make(trans('payment_gateway.sections.general_settings'))
                ->description(trans('payment_gateway.sections.general_settings_description'))
                ->schema([
                    Forms\Components\Toggle::make('paypal_enabled')
                        ->label(trans('payment_gateway.fields.paypal_enabled'))
                        ->helperText(trans('payment_gateway.help.paypal_enabled'))
                        ->default(false),
                ])
                ->columns(1),
        ];
    }

    /**
     * Get bank options from VietQR API.
     */
    private function getBankOptions(): array
    {
        try {
            return $this->getVietQRService()->getBankList();
        } catch (\Exception $e) {
            Log::error('Failed to get bank list for payment gateway configuration', [
                'error' => $e->getMessage(),
            ]);

            Notification::make()
                ->title(trans('payment_gateway.messages.error.bank_list_refresh_failed'))
                ->body($e->getMessage())
                ->warning()
                ->send();

            return [];
        }
    }

    /**
     * Get header actions.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('test_paypal_connection')
                ->label(trans('payment_gateway.actions.test_connection'))
                ->icon('heroicon-o-signal')
                ->color('info')
                ->action(function () {
                    try {
                        $result = $this->getPayPalService()->testConnection();

                        if ($result['success']) {
                            Notification::make()
                                ->title(trans('payment_gateway.messages.success.connection_test_passed'))
                                ->body($result['message'])
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(trans('payment_gateway.messages.error.connection_test_failed'))
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(trans('payment_gateway.messages.error.connection_test_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('clear_cache')
                ->label(trans('payment_gateway.actions.clear_cache'))
                ->icon('heroicon-o-trash')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function () {
                    try {
                        $this->getVietQRService()->clearCache();
                        Notification::make()
                            ->title(trans('payment_gateway.messages.success.cache_cleared'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(trans('payment_gateway.messages.error.cache_clear_failed'))
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
