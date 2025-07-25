<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledFileDeletion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'file_path',
        'storage_disk',
        'description',
        'delete_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delete_at' => 'datetime',
        ];
    }

    /**
     * Scope a query to only include deletions that are due for processing.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('delete_at', '<=', now());
    }

    /**
     * Scope a query to only include deletions for a specific storage disk.
     */
    public function scopeForDisk(Builder $query, string $disk): Builder
    {
        return $query->where('storage_disk', $disk);
    }

    /**
     * Scope a query to only include deletions scheduled for the future.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('delete_at', '>', now());
    }

    /**
     * Check if the deletion is due for processing.
     */
    public function isDue(): bool
    {
        return $this->delete_at->isPast();
    }

    /**
     * Get time remaining until deletion.
     */
    public function getTimeUntilDeletionAttribute(): ?string
    {
        if ($this->delete_at->isPast()) {
            return 'Overdue';
        }

        return $this->delete_at->diffForHumans();
    }
}
