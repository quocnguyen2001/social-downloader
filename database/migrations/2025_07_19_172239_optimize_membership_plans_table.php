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
        Schema::table('membership_plans', function (Blueprint $table) {
            // Add new columns first
            $table->integer('total_request_download')->default(0)->after('weekly_request_limit');
        });

        // Migrate data from monthly_request_limit to total_request_download
        DB::statement('UPDATE membership_plans SET total_request_download = monthly_request_limit WHERE monthly_request_limit IS NOT NULL');

        Schema::table('membership_plans', function (Blueprint $table) {
            // Remove old columns
            $table->dropColumn([
                'bulk_downloads',
                'api_access',
                'concurrent_downloads',
                'monthly_request_limit',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table) {
            // Add back the removed columns
            $table->boolean('bulk_downloads')->default(false)->after('priority_processing');
            $table->boolean('api_access')->default(false)->after('bulk_downloads');
            $table->integer('concurrent_downloads')->default(1)->after('api_access');
            $table->integer('monthly_request_limit')->default(0)->after('weekly_request_limit');
        });

        // Restore data from total_request_download to monthly_request_limit
        DB::statement('UPDATE membership_plans SET monthly_request_limit = total_request_download WHERE total_request_download IS NOT NULL');

        Schema::table('membership_plans', function (Blueprint $table) {
            // Remove the new columns
            $table->dropColumn([
                'total_request_download',
                'expires_at',
            ]);
        });
    }
};
