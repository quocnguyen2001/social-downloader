<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_file_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('file_path');
            $table->string('storage_disk');
            $table->text('description')->nullable();
            $table->timestamp('delete_at');
            $table->timestamps();

            $table->index('delete_at');
            $table->index('storage_disk');
            $table->index(['delete_at', 'storage_disk']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_file_deletions');
    }
};
