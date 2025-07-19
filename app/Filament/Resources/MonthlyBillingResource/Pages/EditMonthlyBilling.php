<?php

namespace App\Filament\Resources\MonthlyBillingResource\Pages;

use App\Filament\Resources\MonthlyBillingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyBilling extends EditRecord
{
    protected static string $resource = MonthlyBillingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
