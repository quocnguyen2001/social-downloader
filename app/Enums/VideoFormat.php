<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum VideoFormat: string implements HasColor, HasIcon, HasLabel
{
    case MP4 = 'mp4';
    case MP3 = 'mp3';
    case WEBM = 'webm';

    public function getLabel(): ?string
    {
        return trans('enums.video_format.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::MP4 => 'primary',
            self::MP3 => 'success',
            self::WEBM => 'warning',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::MP4 => 'heroicon-o-video-camera',
            self::MP3 => 'heroicon-o-musical-note',
            self::WEBM => 'heroicon-o-film',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $format) => [$format->value => $format->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public static function getCheckboxOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $format) => [$format->value => $format->getLabel()])
            ->toArray();
    }

    public function isVideo(): bool
    {
        return in_array($this, [self::MP4, self::WEBM]);
    }

    public function isAudio(): bool
    {
        return $this === self::MP3;
    }

    public function getMimeType(): string
    {
        return match ($this) {
            self::MP4 => 'video/mp4',
            self::MP3 => 'audio/mpeg',
            self::WEBM => 'video/webm',
        };
    }
}
