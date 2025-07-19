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
        Schema::create('api_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');

            // Request details
            $table->string('endpoint');
            $table->string('method', 10);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();

            // Video details
            $table->string('original_url', 1000)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('video_title', 500)->nullable();
            $table->string('requested_quality', 10)->nullable();
            $table->string('requested_format', 10)->nullable();

            // Response details
            $table->integer('status_code');
            $table->integer('response_time')->nullable(); // milliseconds
            $table->bigInteger('file_size')->nullable();
            $table->string('download_url', 1000)->nullable();

            // Billing
            $table->decimal('cost', 10, 4)->default(0); // Cost for this request
            $table->boolean('billed')->default(false);

            $table->timestamps();
            $table->index(['api_key_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_requests');
    }
};
