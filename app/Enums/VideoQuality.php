<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VideoQuality: string implements HasColor, HasIcon, HasLabel
{
    case Q144P = '144p';
    case Q360P = '360p';
    case Q720P = '720p';
    case Q1080P = '1080p';

    public function getLabel(): ?string
    {
        return trans('enums.video_quality.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Q144P => 'gray',
            self::Q360P => 'warning',
            self::Q720P => 'success',
            self::Q1080P => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::Q144P => 'heroicon-o-signal',
            self::Q360P => 'heroicon-o-signal',
            self::Q720P => 'heroicon-o-signal',
            self::Q1080P => 'heroicon-o-signal',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $quality) => [$quality->value => $quality->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public static function getCheckboxOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $quality) => [$quality->value => $quality->getLabel()])
            ->toArray();
    }

    public function getResolution(): int
    {
        return match ($this) {
            self::Q144P => 144,
            self::Q360P => 360,
            self::Q720P => 720,
            self::Q1080P => 1080,
        };
    }
}
