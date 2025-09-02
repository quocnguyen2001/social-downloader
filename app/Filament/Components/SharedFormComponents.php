<?php

namespace App\Filament\Components;

use App\Enums\ApiKeyStatus;
use App\Enums\DownloadSessionStatus;
use App\Enums\Platform;
use App\Enums\VideoFormat;
use App\Enums\VideoQuality;
use Filament\Forms;

class SharedFormComponents
{
    /**
     * Common status select field for API keys.
     */
    public static function apiKeyStatusSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('status')
            ->options(ApiKeyStatus::getOptions())
            ->default(ApiKeyStatus::ACTIVE->value)
            ->required()
            ->label(trans('models.api_key.fields.status'));
    }

    /**
     * Common status select field for download sessions.
     */
    public static function downloadSessionStatusSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('status')
            ->label(trans('messages.labels.status'))
            ->options(DownloadSessionStatus::getOptions())
            ->default(DownloadSessionStatus::PENDING->value)
            ->required();
    }

    /**
     * Common platform select field.
     */
    public static function platformSelect(): Forms\Components\Select
    {
        return Forms\Components\Select::make('platform')
            ->label(trans('messages.labels.platform'))
            ->options(Platform::getOptions())
            ->required();
    }

    /**
     * Common URL input field.
     */
    public static function urlInput(string $name = 'original_url', ?string $label = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->url()
            ->required()
            ->maxLength(1000)
            ->label($label ?? trans('messages.labels.original_url'))
            ->placeholder(trans('messages.placeholders.enter_original_url'));
    }

    /**
     * Common email input field.
     */
    public static function emailInput(string $name = 'email', ?string $label = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->email()
            ->required()
            ->label($label ?? trans('messages.labels.email'))
            ->placeholder(trans('messages.placeholders.enter_email'));
    }

    /**
     * Common name input field.
     */
    public static function nameInput(string $name = 'name', ?string $label = null): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make($name)
            ->required()
            ->maxLength(255)
            ->label($label ?? trans('messages.labels.name'))
            ->placeholder(trans('messages.placeholders.enter_name'));
    }

    /**
     * Common date time picker field.
     */
    public static function dateTimePicker(string $name, ?string $label = null): Forms\Components\DateTimePicker
    {
        return Forms\Components\DateTimePicker::make($name)
            ->label($label ?? trans('messages.labels.'.$name))
            ->nullable();
    }

    /**
     * Common platform permissions checkbox list.
     */
    public static function platformPermissions(): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('allowed_platforms')
            ->options(Platform::getCheckboxOptions())
            ->columns(2)
            ->label(trans('models.api_key.fields.allowed_platforms'));
    }

    /**
     * Common quality permissions checkbox list.
     */
    public static function qualityPermissions(): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('allowed_qualities')
            ->options(VideoQuality::getCheckboxOptions())
            ->columns(4)
            ->label(trans('models.api_key.fields.allowed_qualities'));
    }

    /**
     * Common format permissions checkbox list.
     */
    public static function formatPermissions(): Forms\Components\CheckboxList
    {
        return Forms\Components\CheckboxList::make('allowed_formats')
            ->options(VideoFormat::getCheckboxOptions())
            ->columns(3)
            ->label(trans('models.api_key.fields.allowed_formats'));
    }

    /**
     * Common date range filter form.
     */
    public static function dateRangeFilter(): array
    {
        return [
            Forms\Components\DatePicker::make('created_from')
                ->label(trans('messages.table.filters.created_from')),
            Forms\Components\DatePicker::make('created_until')
                ->label(trans('messages.table.filters.created_until')),
        ];
    }
}
