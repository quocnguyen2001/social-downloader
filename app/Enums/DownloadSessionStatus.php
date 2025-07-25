<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DownloadSessionStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case FETCHING_METADATA = 'fetching_metadata';
    case METADATA_FETCHED = 'metadata_fetched';
    case READY_FOR_DOWNLOAD = 'ready_for_download';

    case FAILED = 'failed';

    public function getLabel(): ?string
    {
        return trans('enums.download_session_status.'.$this->value);
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::FETCHING_METADATA => 'info',
            self::METADATA_FETCHED => 'primary',
            self::READY_FOR_DOWNLOAD => 'success',
            self::FAILED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::FETCHING_METADATA => 'heroicon-o-arrow-path',
            self::METADATA_FETCHED => 'heroicon-o-document-check',
            self::READY_FOR_DOWNLOAD => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-x-circle',
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

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::FETCHING_METADATA, self::METADATA_FETCHED]);
    }

    public function isFinished(): bool
    {
        return $this === self::READY_FOR_DOWNLOAD;
    }

    public function canFetchMetadata(): bool
    {
        return $this === self::PENDING;
    }

    public function canMarkMetadataFetched(): bool
    {
        return $this === self::FETCHING_METADATA;
    }

    public function canMarkReadyForDownload(): bool
    {
        return $this === self::METADATA_FETCHED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isFetchingMetadata(): bool
    {
        return $this === self::FETCHING_METADATA;
    }

    public function isMetadataFetched(): bool
    {
        return $this === self::METADATA_FETCHED;
    }

    public function isReadyForDownload(): bool
    {
        return $this === self::READY_FOR_DOWNLOAD;
    }
}
