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
        Schema::create('download_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->nullable()->constrained('api_keys')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('original_url', 1000);
            $table->enum('platform', ['youtube', 'facebook', 'instagram', 'tiktok']);
            $table->string('video_id', 255)->nullable();
            $table->string('title', 500)->nullable();
            $table->text('thumbnail_path', 1000)->nullable();
            $table->string('thumbnail_disk')->nullable();
            $table->integer('duration')->nullable(); // seconds
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
            $table->index(['api_key_id', 'status']);
            $table->index(['status', 'created_at']);

            // Add indexes for better performance
            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // Create orders table (previously invoices/monthly_billings)
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans')->onDelete('set null');
            $table->foreignUuid('payment_id')->nullable()->constrained('transactions')->onDelete('set null');

            // Order details
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'processing', 'completed'])->default('pending');

            $table->timestamps();

            // Indexes
            $table->index(['status', 'created_at']);
            $table->index(['membership_plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('download_sessions');
    }
};
