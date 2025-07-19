<?php

namespace App\Filament\Resources\DownloadSessionResource\Pages;

use App\Filament\Resources\DownloadSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDownloadSession extends EditRecord
{
    protected static string $resource = DownloadSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
