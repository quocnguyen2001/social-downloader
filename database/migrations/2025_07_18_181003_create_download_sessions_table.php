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
            $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

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

            // Add indexes for better performance
            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        // Create invoices table (previously monthly_billings)
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');
            $table->foreignId('membership_plan_id')->nullable()->constrained('membership_plans')->onDelete('set null');

            // Billing details
            $table->date('billing_month');
            $table->integer('total_requests')->default(0);
            $table->decimal('total_cost', 10, 2)->default(0);

            // Platform breakdown
            $table->integer('youtube_requests')->default(0);
            $table->integer('tiktok_requests')->default(0);
            $table->integer('instagram_requests')->default(0);
            $table->integer('facebook_requests')->default(0);

            // Invoice status
            $table->boolean('invoice_sent')->default(false);
            $table->timestamp('invoice_sent_at')->nullable();

            // Payment status
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['api_key_id', 'billing_month']);
            $table->index(['billing_month', 'paid']);
            $table->index(['invoice_sent', 'paid']);
            $table->unique(['api_key_id', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('download_sessions');
    }
};
