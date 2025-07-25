<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, standardize existing quality values before adding unique constraint
        $this->standardizeQualityValues();

        Schema::table('download_options', function (Blueprint $table) {
            // Remove the type column if it exists
            if (Schema::hasColumn('download_options', 'type')) {
                $table->dropColumn('type');
            }

            // Add unique constraint on download_session_id and quality
            $table->unique(['download_session_id', 'quality'], 'download_options_session_quality_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_options', function (Blueprint $table) {
            // Drop the unique constraint
            $table->dropUnique('download_options_session_quality_unique');

            // Re-add the type column
            $table->enum('type', ['only_audio', 'only_video', 'full'])->after('quality');
        });

        // Restore type values based on quality
        $this->restoreTypeValues();
    }

    /**
     * Standardize quality values and remove duplicates to match new requirements.
     */
    private function standardizeQualityValues(): void
    {
        // Update audio formats
        DB::table('download_options')
            ->where('quality', 'like', '%audio%')
            ->orWhere('quality', 'audio only')
            ->update(['quality' => 'audio']);

        // Update video quality formats - extract height from resolution strings
        $options = DB::table('download_options')
            ->whereNotIn('quality', ['audio'])
            ->get();

        foreach ($options as $option) {
            $standardizedQuality = $this->extractStandardizedQuality($option->quality);
            if ($standardizedQuality !== $option->quality) {
                DB::table('download_options')
                    ->where('id', $option->id)
                    ->update(['quality' => $standardizedQuality]);
            }
        }

        // Remove duplicates - keep the one with smallest file_size for each (download_session_id, quality) pair
        $this->removeDuplicates();
    }

    /**
     * Remove duplicate download options, keeping the one with smallest file_size.
     */
    private function removeDuplicates(): void
    {
        // Find all duplicate groups
        $duplicateGroups = DB::table('download_options')
            ->select('download_session_id', 'quality', DB::raw('COUNT(*) as count'))
            ->groupBy('download_session_id', 'quality')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            // Get all options for this group, ordered by file_size (nulls last)
            $options = DB::table('download_options')
                ->where('download_session_id', $group->download_session_id)
                ->where('quality', $group->quality)
                ->orderByRaw('file_size IS NULL, file_size ASC')
                ->get();

            // Keep the first one (smallest file_size), delete the rest
            $keepId = $options->first()->id;
            $deleteIds = $options->skip(1)->pluck('id')->toArray();

            if (! empty($deleteIds)) {
                DB::table('download_options')
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }
        }
    }

    /**
     * Restore type values for rollback.
     */
    private function restoreTypeValues(): void
    {
        // Set type based on quality
        DB::table('download_options')
            ->where('quality', 'audio')
            ->update(['type' => 'only_audio']);

        DB::table('download_options')
            ->whereNotIn('quality', ['audio'])
            ->update(['type' => 'only_video']); // Assume video-only for non-audio formats
    }

    /**
     * Extract standardized quality from various formats.
     */
    private function extractStandardizedQuality(string $quality): string
    {
        // Handle audio formats
        if (stripos($quality, 'audio') !== false) {
            return 'audio';
        }

        // Extract height from resolution like "1920x1080"
        if (preg_match('/(\d+)x(\d+)/', $quality, $matches)) {
            return $matches[2]; // Return height
        }

        // Handle direct quality labels like "720p"
        if (preg_match('/(\d+)p/', $quality, $matches)) {
            return $matches[1];
        }

        // Extract numbers from quality string
        if (preg_match('/(\d+)/', $quality, $matches)) {
            return $matches[1];
        }

        // Fallback to original quality
        return $quality;
    }
};
