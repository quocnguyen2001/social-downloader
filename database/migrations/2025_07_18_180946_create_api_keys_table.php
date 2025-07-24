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
        // Create API keys table
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name'); // APP/Client name
            $table->string('key_hash')->unique(); // Hashed API key
            $table->string('key_prefix', 10)->default('vd_live_'); // Key prefix
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('tier')->default('basic'); // basic, pro, premium

            // Pricing & Limits
            $table->decimal('price_per_request', 10, 4)->default(0.0500); // Price per request in VND
            $table->integer('daily_limit')->default(1000);
            $table->integer('monthly_limit')->default(30000);

            // Usage tracking
            $table->integer('daily_usage')->default(0);
            $table->integer('monthly_usage')->default(0);
            $table->bigInteger('total_usage')->default(0);
            $table->date('last_reset_daily')->useCurrent();
            $table->date('last_reset_monthly')->useCurrent();

            // Contact & Billing info
            $table->string('contact_email');
            $table->string('billing_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('webhook_url', 500)->nullable();

            // Permissions (JSON fields)
            $table->json('allowed_platforms')->nullable(); // ["youtube", "tiktok", "instagram", "facebook"]
            $table->json('allowed_qualities')->nullable(); // ["144p", "360p", "720p", "1080p"]
            $table->json('allowed_formats')->nullable();   // ["mp4", "mp3", "webm"]

            $table->timestamps();

            // Add indexes for better performance
            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index('tier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
