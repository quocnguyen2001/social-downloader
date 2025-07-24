<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DownloadOptionStatus: string implements HasColor, HasIcon, HasLabel
{
    case DOWNLOADED = 'downloaded';
    case CDN = 'cdn';

    public function getLabel(): ?string
    {
        return trans('enums.download_option_status.' . $this->value);
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::DOWNLOADED => 'success',
            self::CDN => 'info',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::DOWNLOADED => 'heroicon-o-arrow-down-tray',
            self::CDN => 'heroicon-o-cloud',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status) => [$status->value => $status->getLabel()])
            ->toArray();
    }

    public function getBadgeColor(): string
    {
        return $this->getColor();
    }

    public function isDownloaded(): bool
    {
        return $this === self::DOWNLOADED;
    }

    public function isCdn(): bool
    {
        return $this === self::CDN;
    }
}
