<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Generate a new API key
        $newKey = ApiKey::generateKey();
        $data['key_hash'] = hash('sha256', $newKey);
        $data['key_prefix'] = 'vd_live_';

        // Store the plain key to show to user
        $this->plainApiKey = $newKey;

        return $data;
    }

    protected function afterCreate(): void
    {
        // Show the generated API key to the user
        if (isset($this->plainApiKey)) {
            Notification::make()
                ->title('API Key created successfully!')
                ->body("Your new API key: {$this->plainApiKey}")
                ->success()
                ->persistent()
                ->send();
        }
    }

    private $plainApiKey;
}
