<?php

namespace App\Filament\Resources\ApiRequestResource\Pages;

use App\Filament\Resources\ApiRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiRequests extends ListRecords
{
    protected static string $resource = ApiRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
