<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ScheduledFileDeletion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Service for managing scheduled file deletions.
 *
 * This service provides methods to schedule, retrieve, update, and cancel
 * file deletions across different storage disks.
 */
class ScheduledFileDeletionService
{
    /**
     * Schedule a file for deletion.
     *
     * @param  string  $filePath  Full path to the file to be deleted
     * @param  string  $storageDisk  Storage disk identifier where the file is located
     * @param  float  $hours  Number of hours from now when the file should be deleted (default: 24)
     * @param  string|null  $description  Optional description or reason for deletion
     *
     * @throws \Exception
     */
    public function schedule(
        string $filePath,
        string $storageDisk,
        float $hours = 24,
        ?string $description = null
    ): ScheduledFileDeletion {
        try {
            // Validate storage disk exists
            if (! $this->isDiskAvailable($storageDisk)) {
                throw new \InvalidArgumentException("Storage disk '{$storageDisk}' is not available");
            }

            // Validate file path is not empty
            if (empty(trim($filePath))) {
                throw new \InvalidArgumentException('File path cannot be empty');
            }

            // Validate hours is positive
            if ($hours <= 0) {
                throw new \InvalidArgumentException('Hours must be a positive number');
            }

            // Calculate deletion time
            $deleteAt = now()->addHours($hours);

            $scheduledDeletion = ScheduledFileDeletion::create([
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'description' => $description,
                'delete_at' => $deleteAt,
            ]);

            Log::info('File deletion scheduled', [
                'id' => $scheduledDeletion->id,
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'hours_from_now' => $hours,
                'delete_at' => $deleteAt->format('Y-m-d H:i:s'),
                'description' => $description,
            ]);

            return $scheduledDeletion;

        } catch (\Exception $e) {
            Log::error('Failed to schedule file deletion', [
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'hours_from_now' => $hours,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Schedule a file for deletion at a specific date/time.
     *
     * @param  string  $filePath  Full path to the file to be deleted
     * @param  string  $storageDisk  Storage disk identifier where the file is located
     * @param  \DateTime  $deleteAt  Scheduled deletion time
     * @param  string|null  $description  Optional description or reason for deletion
     *
     * @throws \Exception
     */
    public function scheduleAt(
        string $filePath,
        string $storageDisk,
        \DateTime $deleteAt,
        ?string $description = null
    ): ScheduledFileDeletion {
        try {
            // Validate storage disk exists
            if (! $this->isDiskAvailable($storageDisk)) {
                throw new \InvalidArgumentException("Storage disk '{$storageDisk}' is not available");
            }

            // Validate file path is not empty
            if (empty(trim($filePath))) {
                throw new \InvalidArgumentException('File path cannot be empty');
            }

            // Validate deletion time is in the future
            if ($deleteAt <= new \DateTime) {
                throw new \InvalidArgumentException('Deletion time must be in the future');
            }

            $scheduledDeletion = ScheduledFileDeletion::create([
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'description' => $description,
                'delete_at' => $deleteAt,
            ]);

            Log::info('File deletion scheduled at specific time', [
                'id' => $scheduledDeletion->id,
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'delete_at' => $deleteAt->format('Y-m-d H:i:s'),
                'description' => $description,
            ]);

            return $scheduledDeletion;

        } catch (\Exception $e) {
            Log::error('Failed to schedule file deletion at specific time', [
                'file_path' => $filePath,
                'storage_disk' => $storageDisk,
                'delete_at' => $deleteAt->format('Y-m-d H:i:s'),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Find a scheduled deletion by ID.
     */
    public function find(int $id): ?ScheduledFileDeletion
    {
        return ScheduledFileDeletion::find($id);
    }

    /**
     * Get all scheduled deletions.
     *
     * @return Collection<ScheduledFileDeletion>
     */
    public function getAll(): Collection
    {
        return ScheduledFileDeletion::orderBy('delete_at')->get();
    }

    /**
     * Get pending deletions (scheduled for the future).
     *
     * @return Collection<ScheduledFileDeletion>
     */
    public function getPendingDeletions(): Collection
    {
        return ScheduledFileDeletion::pending()->orderBy('delete_at')->get();
    }

    /**
     * Get deletions for a specific storage disk.
     *
     * @param  string  $disk  Storage disk identifier
     * @return Collection<ScheduledFileDeletion>
     */
    public function getDeletionsForDisk(string $disk): Collection
    {
        return ScheduledFileDeletion::forDisk($disk)->orderBy('delete_at')->get();
    }

    /**
     * Get deletions that are due for processing.
     *
     * @return Collection<ScheduledFileDeletion>
     */
    public function getDeletionsDue(): Collection
    {
        return ScheduledFileDeletion::due()->orderBy('delete_at')->get();
    }

    /**
     * Update a scheduled deletion.
     *
     * @throws \Exception
     */
    public function update(int $id, array $data): bool
    {
        try {
            $scheduledDeletion = $this->find($id);

            if (! $scheduledDeletion) {
                throw new \InvalidArgumentException("Scheduled deletion with ID {$id} not found");
            }

            // Validate storage disk if being updated
            if (isset($data['storage_disk']) && ! $this->isDiskAvailable($data['storage_disk'])) {
                throw new \InvalidArgumentException("Storage disk '{$data['storage_disk']}' is not available");
            }

            // Validate file path if being updated
            if (isset($data['file_path']) && empty(trim($data['file_path']))) {
                throw new \InvalidArgumentException('File path cannot be empty');
            }

            // Validate deletion time if being updated
            if (isset($data['delete_at'])) {
                $deleteAt = $data['delete_at'] instanceof \DateTime ? $data['delete_at'] : new \DateTime($data['delete_at']);
                if ($deleteAt <= new \DateTime) {
                    throw new \InvalidArgumentException('Deletion time must be in the future');
                }
            }

            $result = $scheduledDeletion->update($data);

            Log::info('Scheduled deletion updated', [
                'id' => $id,
                'updated_data' => $data,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to update scheduled deletion', [
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a scheduled deletion.
     *
     * @throws \Exception
     */
    public function cancel(int $id): bool
    {
        try {
            $scheduledDeletion = $this->find($id);

            if (! $scheduledDeletion) {
                throw new \InvalidArgumentException("Scheduled deletion with ID {$id} not found");
            }

            $result = $scheduledDeletion->delete();

            Log::info('Scheduled deletion cancelled', [
                'id' => $id,
                'file_path' => $scheduledDeletion->file_path,
                'storage_disk' => $scheduledDeletion->storage_disk,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to cancel scheduled deletion', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process due deletions (actually delete the files).
     *
     * @param  int  $batchSize  Number of deletions to process in one batch
     * @return array Statistics about processed deletions
     */
    public function processDueDeletions(int $batchSize = 50): array
    {
        $stats = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $dueDeletions = ScheduledFileDeletion::due()
            ->limit($batchSize)
            ->get();

        foreach ($dueDeletions as $deletion) {
            $stats['processed']++;

            try {
                DB::beginTransaction();

                // Check if file exists before attempting deletion
                $disk = Storage::disk($deletion->storage_disk);

                if ($disk->exists($deletion->file_path)) {
                    $disk->delete($deletion->file_path);
                    Log::info('File deleted successfully', [
                        'file_path' => $deletion->file_path,
                        'storage_disk' => $deletion->storage_disk,
                    ]);
                } else {
                    Log::warning('File not found during scheduled deletion', [
                        'file_path' => $deletion->file_path,
                        'storage_disk' => $deletion->storage_disk,
                    ]);
                }

                // Remove the scheduled deletion record
                $deletion->delete();
                $stats['successful']++;

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $stats['failed']++;
                $stats['errors'][] = [
                    'id' => $deletion->id,
                    'file_path' => $deletion->file_path,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process scheduled deletion', [
                    'id' => $deletion->id,
                    'file_path' => $deletion->file_path,
                    'storage_disk' => $deletion->storage_disk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Processed due deletions', $stats);

        return $stats;
    }

    /**
     * Check if a storage disk is available.
     */
    private function isDiskAvailable(string $disk): bool
    {
        return in_array($disk, array_keys(config('filesystems.disks')));
    }

    /**
     * Get available storage disks.
     */
    public function getAvailableDisks(): array
    {
        return array_keys(config('filesystems.disks'));
    }
}
