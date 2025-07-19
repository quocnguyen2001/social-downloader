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
        Schema::create('download_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');

            $table->string('original_url', 1000);
            $table->enum('platform', ['youtube', 'facebook', 'instagram', 'tiktok']);
            $table->string('video_id', 255)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('thumbnail_url', 1000)->nullable();
            $table->integer('duration')->nullable(); // seconds
            $table->string('quality', 10);
            $table->string('format', 10);
            $table->bigInteger('file_size')->nullable();
            $table->text('download_url')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->index(['api_key_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_sessions');
    }
};
