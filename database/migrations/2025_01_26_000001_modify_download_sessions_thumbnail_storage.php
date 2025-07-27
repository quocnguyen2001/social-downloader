<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('download_sessions', function (Blueprint $table) {
            // Remove the existing thumbnail_url column
            $table->dropColumn('thumbnail_url');

            // Add new columns for S3 storage
            $table->string('thumbnail_path')->nullable()->after('title');
            $table->string('thumbnail_disk')->nullable()->after('thumbnail_path');

            // Add index for better performance when querying by thumbnail existence
            $table->index(['thumbnail_path', 'thumbnail_disk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_sessions', function (Blueprint $table) {
            // Drop the new columns
            $table->dropIndex(['thumbnail_path', 'thumbnail_disk']);
            $table->dropColumn(['thumbnail_path', 'thumbnail_disk']);

            // Re-add the original thumbnail_url column
            $table->text('thumbnail_url')->nullable()->after('title');
        });
    }
};
