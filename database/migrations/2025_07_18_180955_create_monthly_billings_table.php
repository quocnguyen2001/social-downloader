<?php

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
        Schema::create('monthly_billings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_key_id')->constrained('api_keys')->onDelete('cascade');

            $table->date('billing_month'); // First day of month
            $table->integer('total_requests')->default(0);
            $table->decimal('total_cost', 12, 2)->default(0.00);

            // Platform breakdown
            $table->integer('youtube_requests')->default(0);
            $table->integer('tiktok_requests')->default(0);
            $table->integer('instagram_requests')->default(0);
            $table->integer('facebook_requests')->default(0);

            // Payment tracking
            $table->boolean('invoice_sent')->default(false);
            $table->timestamp('invoice_sent_at')->nullable();
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 50)->nullable();

            $table->timestamps();
            $table->unique(['api_key_id', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_billings');
    }
};
