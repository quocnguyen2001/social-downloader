<?php

namespace App\Filament\Resources\MonthlyBillingResource\Pages;

use App\Filament\Resources\MonthlyBillingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyBillings extends ListRecords
{
    protected static string $resource = MonthlyBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
