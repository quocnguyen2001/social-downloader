<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DownloadOptionType: string implements HasColor, HasIcon, HasLabel
{
    case ONLY_AUDIO = 'only_audio';
    case ONLY_VIDEO = 'only_video';
    case FULL = 'full';

    public function getLabel(): ?string
    {
        return trans('enums.download_option_type.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ONLY_AUDIO => 'success',
            self::ONLY_VIDEO => 'warning',
            self::FULL => 'primary',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::ONLY_AUDIO => 'heroicon-o-musical-note',
            self::ONLY_VIDEO => 'heroicon-o-video-camera-slash',
            self::FULL => 'heroicon-o-video-camera',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public function isAudioOnly(): bool
    {
        return $this === self::ONLY_AUDIO;
    }

    public function isVideoOnly(): bool
    {
        return $this === self::ONLY_VIDEO;
    }

    public function isFull(): bool
    {
        return $this === self::FULL;
    }

    public function hasAudio(): bool
    {
        return in_array($this, [self::ONLY_AUDIO, self::FULL]);
    }

    public function hasVideo(): bool
    {
        return in_array($this, [self::ONLY_VIDEO, self::FULL]);
    }
}
