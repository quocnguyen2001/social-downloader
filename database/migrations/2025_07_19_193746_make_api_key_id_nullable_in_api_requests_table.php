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
        Schema::table('api_requests', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['api_key_id']);

            // Modify the column to be nullable
            $table->uuid('api_key_id')->nullable()->change();

            // Re-add the foreign key constraint with nullable support
            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_requests', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['api_key_id']);

            // Make the column non-nullable again
            $table->uuid('api_key_id')->nullable(false)->change();

            // Re-add the foreign key constraint
            $table->foreign('api_key_id')->references('id')->on('api_keys')->onDelete('cascade');
        });
    }
};
