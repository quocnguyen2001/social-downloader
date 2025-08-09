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
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly, lifetime

            // Request limits (consolidated final schema)
            $table->integer('daily_request_limit')->default(0); // 0 = unlimited
            $table->integer('total_request_download')->default(0); // 0 = unlimited

            // Features
            $table->json('allowed_platforms')->nullable(); // ['youtube', 'tiktok', 'instagram', 'facebook']
            $table->json('allowed_qualities')->nullable(); // ['144p', '360p', '720p', '1080p']
            $table->json('allowed_formats')->nullable(); // ['mp4', 'webm', 'mp3']

            // Additional features (final schema after optimization)
            $table->boolean('priority_processing')->default(false);

            // Plan status and ordering
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['is_active', 'sort_order']);
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
