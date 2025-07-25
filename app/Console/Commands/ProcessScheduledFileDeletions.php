<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ScheduledFileDeletionService;
use Illuminate\Console\Command;

class ProcessScheduledFileDeletions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scheduled-deletions:process
                            {--batch-size=50 : Number of deletions to process in one batch}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled file deletions that are due';

    /**
     * Execute the console command.
     */
    public function handle(ScheduledFileDeletionService $service): int
    {
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        $this->info('Processing scheduled file deletions...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will actually be deleted');
        }

        try {
            // Get due deletions for preview
            $dueDeletions = $service->getDeletionsDue();

            if ($dueDeletions->isEmpty()) {
                $this->info('No scheduled deletions are due for processing.');

                return 0;
            }

            $this->info("Found {$dueDeletions->count()} deletion(s) due for processing:");

            // Show what will be processed
            $this->table(
                ['ID', 'File Path', 'Storage Disk', 'Scheduled At', 'Description'],
                $dueDeletions->map(function ($deletion) {
                    return [
                        $deletion->id,
                        $deletion->file_path,
                        $deletion->storage_disk,
                        $deletion->delete_at->format('Y-m-d H:i:s'),
                        $deletion->description ?? 'N/A',
                    ];
                })->toArray()
            );

            if ($dryRun) {
                $this->info('DRY RUN completed. No files were deleted.');

                return 0;
            }

            // Confirm before proceeding
            if (! $this->confirm('Do you want to proceed with deleting these files?')) {
                $this->info('Operation cancelled.');

                return 0;
            }

            // Process the deletions
            $stats = $service->processDueDeletions($batchSize);

            // Display results
            $this->info('Processing completed:');
            $this->line("  Processed: {$stats['processed']}");
            $this->line("  Successful: {$stats['successful']}");
            $this->line("  Failed: {$stats['failed']}");

            if ($stats['failed'] > 0) {
                $this->warn('Some deletions failed:');
                foreach ($stats['errors'] as $error) {
                    $this->error("  ID {$error['id']}: {$error['file_path']} - {$error['error']}");
                }
            }

            if ($stats['successful'] > 0) {
                $this->info("✓ Successfully processed {$stats['successful']} scheduled deletion(s)");
            }

            return $stats['failed'] > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("Failed to process scheduled deletions: {$e->getMessage()}");

            return 1;
        }
    }
}
