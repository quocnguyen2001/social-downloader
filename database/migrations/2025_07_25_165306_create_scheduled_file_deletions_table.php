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
        Schema::create('scheduled_file_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('file_path'); // Full path to the file to be deleted
            $table->string('storage_disk'); // Storage disk identifier where the file is located
            $table->text('description')->nullable(); // Optional description or reason for deletion
            $table->timestamp('delete_at'); // Scheduled deletion time
            $table->timestamps();

            // Add indexes for better performance
            $table->index('delete_at');
            $table->index('storage_disk');
            $table->index(['delete_at', 'storage_disk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_file_deletions');
    }
};
