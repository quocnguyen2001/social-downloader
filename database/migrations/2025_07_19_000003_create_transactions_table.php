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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Required relationships
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Customer details
            $table->string('customer_name'); // Customer's name
            $table->string('customer_email'); // Customer's email address

            // Transaction identifiers
            $table->string('charge_id'); // Payment provider charge ID
            $table->string('order_id'); // Order identifier

            // Transaction details
            $table->string('payment_method'); // Payment method used
            $table->string('currency', 3)->default('USD'); // Transaction currency
            $table->text('payment_logs')->nullable(); // External payment provider logs (PayPal, etc.)

            // Additional transaction metadata
            $table->decimal('amount', 12, 2)->nullable(); // Transaction amount
            $table->string('status')->default('pending'); // Transaction status

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['order_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['charge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
