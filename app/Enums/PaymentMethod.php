<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasColor, HasIcon, HasLabel
{
    case BANK_TRANSFER = 'bank_transfer';
    case CREDIT_CARD = 'credit_card';
    case PAYPAL = 'paypal';
    case CRYPTO = 'crypto';

    public function getLabel(): ?string
    {
        return trans('enums.payment_method.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::BANK_TRANSFER => 'primary',
            self::CREDIT_CARD => 'success',
            self::PAYPAL => 'warning',
            self::CRYPTO => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::BANK_TRANSFER => 'heroicon-o-building-library',
            self::CREDIT_CARD => 'heroicon-o-credit-card',
            self::PAYPAL => 'heroicon-o-currency-dollar',
            self::CRYPTO => 'heroicon-o-currency-bitcoin',
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
        return in_array($this, [self::CREDIT_CARD, self::PAYPAL]);
    }

    public function requiresVerification(): bool
    {
        return in_array($this, [self::BANK_TRANSFER, self::CRYPTO]);
    }
}
