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
        // Create membership plans table
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly, lifetime

            // Request limits
            $table->integer('daily_request_limit')->default(0); // 0 = unlimited
            $table->integer('weekly_request_limit')->default(0); // 0 = unlimited
            $table->integer('monthly_request_limit')->default(0); // 0 = unlimited

            // Features
            $table->json('allowed_platforms')->nullable(); // ['youtube', 'tiktok', 'instagram', 'facebook']
            $table->json('allowed_qualities')->nullable(); // ['144p', '360p', '720p', '1080p']
            $table->json('allowed_formats')->nullable(); // ['mp4', 'webm', 'mp3']

            // Additional features
            $table->boolean('priority_processing')->default(false);
            $table->boolean('bulk_downloads')->default(false);
            $table->boolean('api_access')->default(false);
            $table->integer('concurrent_downloads')->default(1);
            $table->integer('max_file_size_mb')->default(100); // in MB

            // Plan status and ordering
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('billing_cycle');
        });

        // Add membership plan fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('membership_plan_id')->nullable()->after('email_verified_at')->constrained()->onDelete('set null');
            $table->timestamp('membership_started_at')->nullable()->after('membership_plan_id');
            $table->timestamp('membership_expires_at')->nullable()->after('membership_started_at');

            // Add indexes for better performance
            $table->index('membership_plan_id');
            $table->index(['membership_expires_at', 'membership_plan_id']);
        });

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

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membership_plan_id']);
            $table->dropIndex(['membership_plan_id']);
            $table->dropIndex(['membership_expires_at', 'membership_plan_id']);
            $table->dropColumn(['membership_plan_id', 'membership_started_at', 'membership_expires_at']);
        });

        Schema::dropIfExists('membership_plans');
    }
};
