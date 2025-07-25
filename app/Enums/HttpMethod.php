<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum HttpMethod: string implements HasColor, HasIcon, HasLabel
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';

    public function getLabel(): ?string
    {
        return trans('enums.http_method.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::GET => 'success',
            self::POST => 'primary',
            self::PUT => 'warning',
            self::DELETE => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::GET => 'heroicon-o-arrow-down',
            self::POST => 'heroicon-o-plus',
            self::PUT => 'heroicon-o-pencil',
            self::DELETE => 'heroicon-o-trash',
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

    public function isReadOnly(): bool
    {
        return $this === self::GET;
    }

    public function isWriteOperation(): bool
    {
        return in_array($this, [self::POST, self::PUT, self::DELETE]);
    }
}
