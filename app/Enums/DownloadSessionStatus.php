<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DownloadSessionStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    public function getLabel(): ?string
    {
        return trans('enums.download_session_status.' . $this->value);
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PROCESSING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::PROCESSING => 'heroicon-o-arrow-path',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::FAILED => 'heroicon-o-x-circle',
            self::EXPIRED => 'heroicon-o-archive-box-x-mark',
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
        return in_array($this, [self::PENDING, self::PROCESSING, self::COMPLETED]);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::EXPIRED]);
    }

    public function canRetry(): bool
    {
        return $this === self::FAILED;
    }

    public function canMarkCompleted(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }

    public function canMarkFailed(): bool
    {
        return in_array($this, [self::PENDING, self::PROCESSING]);
    }
}
