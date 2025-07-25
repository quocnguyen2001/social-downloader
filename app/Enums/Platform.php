<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasColor, HasIcon, HasLabel
{
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';
    case INSTAGRAM = 'instagram';
    case FACEBOOK = 'facebook';

    public function getLabel(): ?string
    {
        return trans('enums.platform.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::YOUTUBE => 'danger',
            self::TIKTOK => 'warning',
            self::INSTAGRAM => 'success',
            self::FACEBOOK => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::YOUTUBE => 'heroicon-o-play',
            self::TIKTOK => 'heroicon-o-musical-note',
            self::INSTAGRAM => 'heroicon-o-camera',
            self::FACEBOOK => 'heroicon-o-users',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $platform) => [$platform->value => $platform->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public static function getCheckboxOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $platform) => [$platform->value => $platform->getLabel()])
            ->toArray();
    }
}
