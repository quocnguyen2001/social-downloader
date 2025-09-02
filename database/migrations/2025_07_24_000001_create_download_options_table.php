<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('download_session_id')->constrained('download_sessions')->onDelete('cascade');
            $table->string('cdn_id')->nullable();
            $table->string('mime_type');
            $table->bigInteger('file_size')->nullable();
            $table->integer('estimated_download_time')->nullable();
            $table->string('storage_disk')->nullable();
            $table->string('storage_file_path')->nullable();
            $table->string('quality');
            $table->text('download_cdn_url')->nullable();
            $table->string('status')->default('cdn');
            $table->timestamps();

            $table->index(['download_session_id', 'status']);
            $table->index(['download_session_id', 'quality']);
            $table->index(['status', 'created_at']);
            $table->index('quality');

            $table->unique(['download_session_id', 'quality'], 'download_options_session_quality_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_options');
    }
};
