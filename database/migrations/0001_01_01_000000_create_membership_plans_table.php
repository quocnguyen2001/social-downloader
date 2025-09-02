<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_cycle')->default('monthly');
            $table->integer('daily_request_limit')->default(0);
            $table->integer('total_request_download')->default(0);
            $table->json('allowed_platforms')->nullable();
            $table->json('allowed_qualities')->nullable();
            $table->boolean('priority_processing')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_plans');
    }
};
