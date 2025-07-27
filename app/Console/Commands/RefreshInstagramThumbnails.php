<?php

namespace App\Console\Commands;

use App\Models\DownloadSession;
use App\Services\VideoExtraction\YtDlpService;
use Illuminate\Console\Command;

class RefreshInstagramThumbnails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'instagram:refresh-thumbnails 
                            {--days=7 : Number of days to look back for sessions}
                            {--force : Force refresh all Instagram thumbnails regardless of validity}
                            {--dry-run : Show what would be refreshed without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Refresh expired or invalid Instagram thumbnail URLs';

    /**
     * Execute the console command.
     */
    public function handle(YtDlpService $ytDlpService): int
    {
        $days = (int) $this->option('days');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $this->info("Refreshing Instagram thumbnails from the last {$days} days...");

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get Instagram sessions from the specified time period
        $sessions = DownloadSession::where('platform', 'instagram')
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('thumbnail_url')
            ->get();

        if ($sessions->isEmpty()) {
            $this->info('No Instagram sessions found in the specified time period.');

            return 0;
        }

        $this->info("Found {$sessions->count()} Instagram sessions to check.");

        $refreshed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($sessions as $session) {
            $shouldRefresh = $force;

            if (! $force) {
                // Check if thumbnail URL needs refreshing
                $shouldRefresh = $this->shouldRefreshThumbnail($session->thumbnail_url);
            }

            if (! $shouldRefresh) {
                $skipped++;

                continue;
            }

            $this->line("Refreshing thumbnail for session {$session->id}...");

            if ($dryRun) {
                $this->info("  [DRY RUN] Would refresh: {$session->original_url}");

                continue;
            }

            try {
                $newThumbnailUrl = $ytDlpService->refreshInstagramThumbnail($session->original_url);

                if ($newThumbnailUrl) {
                    $session->update(['thumbnail_url' => $newThumbnailUrl]);
                    $this->info('  ✓ Refreshed successfully');
                    $refreshed++;
                } else {
                    $this->error('  ✗ Failed to get new thumbnail URL');
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("  ✗ Error: {$e->getMessage()}");
                $failed++;
            }
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Refreshed', $refreshed],
                ['Failed', $failed],
                ['Skipped', $skipped],
                ['Total', $sessions->count()],
            ]
        );

        if ($failed > 0) {
            $this->warn('Some thumbnails failed to refresh. Check the logs for details.');

            return 1;
        }

        $this->info('Instagram thumbnail refresh completed successfully!');

        return 0;
    }

    /**
     * Check if a thumbnail URL should be refreshed.
     */
    private function shouldRefreshThumbnail(string $url): bool
    {
        // Check if it's an Instagram URL
        if (! str_contains($url, 'instagram.') && ! str_contains($url, 'fbcdn.net')) {
            return false;
        }

        $parsedUrl = parse_url($url);

        if (! isset($parsedUrl['query'])) {
            return true; // Missing query parameters
        }

        parse_str($parsedUrl['query'], $queryParams);

        // Check for essential parameters
        $requiredParams = ['_nc_ht', '_nc_cat', 'oh', 'oe'];
        foreach ($requiredParams as $param) {
            if (! isset($queryParams[$param])) {
                return true; // Missing required parameter
            }
        }

        // Check if URL is expired (oe parameter is expiration timestamp)
        if (isset($queryParams['oe'])) {
            $expirationTimestamp = hexdec($queryParams['oe']);
            if ($expirationTimestamp > 0 && $expirationTimestamp < time()) {
                return true; // URL is expired
            }
        }

        return false; // URL appears to be valid
    }
}
