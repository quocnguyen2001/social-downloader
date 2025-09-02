<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->nullable()->constrained('api_keys')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('endpoint');
            $table->string('method', 10);
            $table->ipAddress()->nullable();
            $table->text('user_agent')->nullable();

            $table->text('original_url')->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('video_title', 500)->nullable();
            $table->string('requested_quality', 10)->nullable();
            $table->string('requested_format', 10)->nullable();

            $table->integer('status_code');
            $table->integer('response_time')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->text('download_url')->nullable();

            $table->decimal('cost', 10, 4)->default(0);
            $table->boolean('billed')->default(false);

            $table->timestamps();
            $table->index(['api_key_id', 'created_at']);

            $table->index('user_id');
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'billed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
