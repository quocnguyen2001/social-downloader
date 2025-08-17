<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DownloadOptionStatus;
use App\Models\DownloadSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredDownloads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'downloads:cleanup-expired
                            {--batch-size=50 : Number of sessions to process in one batch}
                            {--dry-run : Show what would be cleaned without actually cleaning}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleanup expired download sessions and their associated files';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        $this->info('Starting cleanup of expired download sessions...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No files will actually be deleted');
        }

        try {
            // Get expired download sessions with their downloaded options
            $expiredSessions = DownloadSession::query()
                ->expired()
                ->with(['downloadOptions' => function ($query) {
                    $query->where('status', DownloadOptionStatus::DOWNLOADED)
                        ->whereNotNull('storage_disk')
                        ->whereNotNull('storage_file_path');
                }])
                ->limit($batchSize)
                ->get();

            if ($expiredSessions->isEmpty()) {
                $this->info('No expired download sessions found.');

                return 0;
            }

            $this->info("Found {$expiredSessions->count()} expired session(s) to process.");

            // Count total files to be processed
            $totalFiles = $expiredSessions->sum(function ($session) {
                return $session->downloadOptions->count();
            });

            if ($totalFiles === 0) {
                $this->info('No downloaded files found in expired sessions.');

                return 0;
            }

            $this->info("Found {$totalFiles} file(s) to cleanup.");

            // Show preview of what will be processed
            if ($this->output->isVerbose() || $dryRun) {
                $this->showPreview($expiredSessions);
            }

            if ($dryRun) {
                $this->info('DRY RUN completed. No files were deleted.');

                return 0;
            }

            // Process the cleanup
            $stats = $this->processCleanup($expiredSessions);

            // Display results
            $this->displayResults($stats);

            return $stats['failed'] > 0 ? 1 : 0;

        } catch (\Exception $e) {
            $this->error("Failed to cleanup expired downloads: {$e->getMessage()}");
            Log::error('Cleanup expired downloads failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Show preview of what will be processed.
     */
    private function showPreview($expiredSessions): void
    {
        $this->info('Sessions to be processed:');

        $tableData = [];
        foreach ($expiredSessions as $session) {
            foreach ($session->downloadOptions as $option) {
                $tableData[] = [
                    $session->id,
                    $session->title ?? 'N/A',
                    $session->expires_at->format('Y-m-d H:i:s'),
                    $option->storage_disk,
                    $option->storage_file_path,
                    $option->formatted_file_size,
                ];
            }
        }

        $this->table(
            ['Session ID', 'Title', 'Expired At', 'Storage Disk', 'File Path', 'File Size'],
            $tableData
        );
    }

    /**
     * Process the cleanup of expired downloads.
     */
    private function processCleanup($expiredSessions): array
    {
        $stats = [
            'sessions_processed' => 0,
            'files_processed' => 0,
            'files_deleted' => 0,
            'files_failed' => 0,
            'database_updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $progressBar = $this->output->createProgressBar($expiredSessions->count());
        $progressBar->setFormat('Processing: %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        foreach ($expiredSessions as $session) {
            $progressBar->setMessage("Session: {$session->id}");

            try {
                DB::beginTransaction();

                $sessionStats = $this->processSession($session);

                $stats['sessions_processed']++;
                $stats['files_processed'] += $sessionStats['files_processed'];
                $stats['files_deleted'] += $sessionStats['files_deleted'];
                $stats['files_failed'] += $sessionStats['files_failed'];
                $stats['database_updated'] += $sessionStats['database_updated'];

                // Merge errors
                $stats['errors'] = array_merge($stats['errors'], $sessionStats['errors']);

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $stats['failed']++;
                $stats['errors'][] = [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process expired session', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $stats;
    }

    /**
     * Process a single session.
     */
    private function processSession(DownloadSession $session): array
    {
        $stats = [
            'files_processed' => 0,
            'files_deleted' => 0,
            'files_failed' => 0,
            'database_updated' => 0,
            'errors' => [],
        ];

        foreach ($session->downloadOptions as $option) {
            $stats['files_processed']++;

            try {
                // Check if file exists before attempting deletion
                $fileExists = Storage::disk($option->storage_disk)->exists($option->storage_file_path);

                if ($fileExists) {
                    // Delete the physical file
                    $deleted = Storage::disk($option->storage_disk)->delete($option->storage_file_path);

                    if ($deleted) {
                        $stats['files_deleted']++;
                        Log::info('File deleted successfully', [
                            'session_id' => $session->id,
                            'option_id' => $option->id,
                            'storage_disk' => $option->storage_disk,
                            'file_path' => $option->storage_file_path,
                        ]);
                    } else {
                        $stats['files_failed']++;
                        $stats['errors'][] = [
                            'session_id' => $session->id,
                            'option_id' => $option->id,
                            'file_path' => $option->storage_file_path,
                            'error' => 'Failed to delete file from storage',
                        ];
                    }
                } else {
                    // File doesn't exist, but we'll still update the database
                    Log::warning('File not found during cleanup', [
                        'session_id' => $session->id,
                        'option_id' => $option->id,
                        'storage_disk' => $option->storage_disk,
                        'file_path' => $option->storage_file_path,
                    ]);
                }

                // Update the database record regardless of file deletion result
                $option->update([
                    'storage_disk' => null,
                    'storage_file_path' => null,
                    'status' => DownloadOptionStatus::CDN,
                ]);

                $stats['database_updated']++;

            } catch (\Exception $e) {
                $stats['files_failed']++;
                $stats['errors'][] = [
                    'session_id' => $session->id,
                    'option_id' => $option->id,
                    'file_path' => $option->storage_file_path,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process download option', [
                    'session_id' => $session->id,
                    'option_id' => $option->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Display the cleanup results.
     */
    private function displayResults(array $stats): void
    {
        $this->info('Cleanup completed:');
        $this->line("  Sessions processed: {$stats['sessions_processed']}");
        $this->line("  Files processed: {$stats['files_processed']}");
        $this->line("  Files deleted: {$stats['files_deleted']}");
        $this->line("  Database records updated: {$stats['database_updated']}");
        $this->line("  Files failed: {$stats['files_failed']}");
        $this->line("  Sessions failed: {$stats['failed']}");

        if ($stats['files_failed'] > 0 || $stats['failed'] > 0) {
            $this->warn('Some operations failed:');
            foreach ($stats['errors'] as $error) {
                if (isset($error['session_id'])) {
                    $this->error("  Session {$error['session_id']}: {$error['error']}");
                } else {
                    $this->error("  Option {$error['option_id']}: {$error['file_path']} - {$error['error']}");
                }
            }
        }

        if ($stats['files_deleted'] > 0) {
            $this->info("✓ Successfully cleaned up {$stats['files_deleted']} file(s) from {$stats['sessions_processed']} expired session(s)");
        }
    }
}
