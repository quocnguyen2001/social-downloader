<?php

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
        Schema::table('download_options', function (Blueprint $table) {
            // Modify the status enum to include new values
            $table->enum('status', ['downloaded', 'cdn', 'processing', 'failed'])
                ->default('cdn')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('download_options', function (Blueprint $table) {
            // Revert back to original enum values
            $table->enum('status', ['downloaded', 'cdn'])
                ->default('cdn')
                ->change();
        });
    }
};
