<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('key_hash')->unique();
            $table->string('key_prefix', 10)->default('vd_live_');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');

            $table->decimal('price_per_request', 10, 4)->default(0);
            $table->integer('daily_limit')->default(1000);
            $table->integer('monthly_limit')->default(30000);

            $table->integer('daily_usage')->default(0);
            $table->integer('monthly_usage')->default(0);
            $table->bigInteger('total_usage')->default(0);
            $table->date('last_reset_daily')->useCurrent();
            $table->date('last_reset_monthly')->useCurrent();

            $table->timestamps();

            $table->index('user_id');
            $table->index(['user_id', 'status']);
            $table->index('tier');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
