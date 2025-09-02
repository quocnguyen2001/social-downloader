<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('charge_id')->unique();
            $table->string('order_id');
            $table->string('payment_method');
            $table->string('currency', 3)->default('VND');
            $table->text('payment_logs')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
