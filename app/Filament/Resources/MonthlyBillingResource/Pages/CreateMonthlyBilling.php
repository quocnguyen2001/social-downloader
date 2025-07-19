<?php

namespace App\Filament\Resources\MonthlyBillingResource\Pages;

use App\Filament\Resources\MonthlyBillingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateMonthlyBilling extends CreateRecord
{
    protected static string $resource = MonthlyBillingResource::class;
}
