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
            $table->foreignUuid('invoice_id')->constrained('invoices')->onDelete('cascade');

            // Transaction details
            $table->string('email'); // User's email for transaction
            $table->string('payment_method'); // Payment method used
            $table->string('currency', 3)->default('USD'); // Transaction currency
            $table->text('payment_logs')->nullable(); // External payment provider logs (PayPal, etc.)

            // Additional transaction metadata
            $table->decimal('amount', 12, 2)->nullable(); // Transaction amount
            $table->string('transaction_id')->nullable(); // External transaction ID
            $table->string('status')->default('pending'); // Transaction status
            $table->timestamp('processed_at')->nullable(); // When transaction was processed

            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'created_at']);
            $table->index(['invoice_id', 'status']);
            $table->index(['status', 'created_at']);
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
