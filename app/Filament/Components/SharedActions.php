<?php

namespace App\Filament\Components;

use App\Enums\ApiKeyStatus;
use App\Enums\DownloadSessionStatus;
use App\Models\ApiKey;
use App\Models\DownloadSession;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;

class SharedActions
{
    /**
     * Common activate action for API keys.
     */
    public static function activateApiKeyAction(): Action
    {
        return Action::make('activate')
            ->label(trans('actions.api_key.activate'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->action(function (ApiKey $record) {
                $record->update(['status' => ApiKeyStatus::ACTIVE]);
                Notification::make()
                    ->title(trans('messages.success.api_key_activated'))
                    ->success()
                    ->send();
            })
            ->visible(fn (ApiKey $record) => $record->status !== ApiKeyStatus::ACTIVE);
    }

    /**
     * Common deactivate action for API keys.
     */
    public static function deactivateApiKeyAction(): Action
    {
        return Action::make('deactivate')
            ->label(trans('actions.api_key.deactivate'))
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->action(function (ApiKey $record) {
                $record->update(['status' => ApiKeyStatus::INACTIVE]);
                Notification::make()
                    ->title(trans('messages.success.api_key_deactivated'))
                    ->warning()
                    ->send();
            })
            ->visible(fn (ApiKey $record) => $record->status === ApiKeyStatus::ACTIVE);
    }

    /**
     * Common suspend action for API keys.
     */
    public static function suspendApiKeyAction(): Action
    {
        return Action::make('suspend')
            ->label(trans('actions.api_key.suspend'))
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (ApiKey $record) {
                $record->update(['status' => ApiKeyStatus::SUSPENDED]);
                Notification::make()
                    ->title(trans('messages.success.api_key_suspended'))
                    ->danger()
                    ->send();
            })
            ->visible(fn (ApiKey $record) => $record->status !== ApiKeyStatus::SUSPENDED);
    }

    /**
     * Common reset usage action for API keys.
     */
    public static function resetUsageAction(): Action
    {
        return Action::make('reset_usage')
            ->label(trans('actions.api_key.reset_usage'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->requiresConfirmation()
            ->action(function (ApiKey $record) {
                $record->update([
                    'daily_usage' => 0,
                    'monthly_usage' => 0,
                    'last_reset_daily' => now()->toDateString(),
                    'last_reset_monthly' => now()->startOfMonth()->toDateString(),
                ]);
                Notification::make()
                    ->title(trans('messages.success.usage_reset'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Common mark as failed action for download sessions.
     */
    public static function markAsFailedAction(): Action
    {
        return Action::make('mark_failed')
            ->label(trans('actions.download_session.mark_failed'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->form([
                Forms\Components\Textarea::make('error_message')
                    ->required()
                    ->label(trans('messages.labels.error_message')),
            ])
            ->action(function (DownloadSession $record, array $data) {
                $record->markAsFailed($data['error_message']);
                Notification::make()
                    ->title(trans('messages.success.download_session_marked_failed'))
                    ->warning()
                    ->send();
            })
            ->visible(fn (DownloadSession $record) => in_array($record->status, [
                DownloadSessionStatus::PENDING,
                DownloadSessionStatus::FETCHING_METADATA,
            ]));
    }

    /**
     * Common mark as expired action for download sessions.
     */
    public static function markAsExpiredAction(): Action
    {
        return Action::make('mark_expired')
            ->label(trans('actions.download_session.mark_expired'))
            ->icon('heroicon-o-clock')
            ->color('secondary')
            ->requiresConfirmation()
            ->action(function (DownloadSession $record) {
                $record->markAsExpired();
                Notification::make()
                    ->title(trans('messages.success.download_session_marked_expired'))
                    ->warning()
                    ->send();
            })
            ->visible(fn (DownloadSession $record) => ! $record->isExpired());
    }

    /**
     * Common bulk activate action for API keys.
     */
    public static function bulkActivateApiKeysAction(): BulkAction
    {
        return BulkAction::make('activate')
            ->label(trans('actions.api_key.bulk_activate'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->action(function ($records) {
                $records->each->update(['status' => ApiKeyStatus::ACTIVE]);
                Notification::make()
                    ->title(trans('messages.success.api_keys_activated'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Common bulk deactivate action for API keys.
     */
    public static function bulkDeactivateApiKeysAction(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->label(trans('actions.api_key.bulk_deactivate'))
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->action(function ($records) {
                $records->each->update(['status' => ApiKeyStatus::INACTIVE]);
                Notification::make()
                    ->title(trans('messages.success.api_keys_deactivated'))
                    ->warning()
                    ->send();
            });
    }
}
