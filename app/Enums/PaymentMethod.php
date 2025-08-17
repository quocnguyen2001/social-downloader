<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case BANK_TRANSFER = 'bank_transfer';
    case PAYPAL = 'paypal';

    public function getLabel(): ?string
    {
        return trans('enums.payment_method.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BANK_TRANSFER => 'primary',
            self::PAYPAL => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'heroicon-o-building-library',
            self::PAYPAL => 'heroicon-o-currency-dollar',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $method) => [$method->value => $method->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public function isInstant(): bool
    {
        return in_array($this, [self::PAYPAL]);
    }

    public function requiresVerification(): bool
    {
        return in_array($this, [self::BANK_TRANSFER]);
    }
}
