<?php

namespace App\Filament\Resources\DownloadSessionResource\Pages;

use App\Filament\Resources\DownloadSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListDownloadSessions extends ListRecords
{
    protected static string $resource = DownloadSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Create action removed as per requirements
        ];
    }
}
