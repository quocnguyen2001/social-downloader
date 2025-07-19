<?php

namespace App\Filament\Resources\ApiRequestResource\Pages;

use App\Filament\Resources\ApiRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiRequest extends EditRecord
{
    protected static string $resource = ApiRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
