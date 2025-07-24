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
        Schema::create('download_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('download_session_id')->constrained('download_sessions')->onDelete('cascade');
            
            // CDN and file information
            $table->string('cdn_id')->nullable();
            $table->string('mime_type');
            $table->bigInteger('file_size')->nullable();
            $table->integer('estimated_download_time')->nullable(); // in seconds
            
            // Storage information (for downloaded content)
            $table->string('storage_disk')->nullable();
            $table->string('storage_file_path')->nullable();
            
            // Content specifications
            $table->string('quality'); // 144p, 360p, 720p, 1080p, etc.
            $table->enum('type', ['only_audio', 'only_video', 'full']);
            
            // Download URL (for CDN content)
            $table->text('download_cdn_url')->nullable();
            
            // Status
            $table->enum('status', ['downloaded', 'cdn'])->default('cdn');
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['download_session_id', 'status']);
            $table->index(['download_session_id', 'type']);
            $table->index(['download_session_id', 'quality']);
            $table->index(['status', 'created_at']);
            $table->index('quality');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_options');
    }
};
