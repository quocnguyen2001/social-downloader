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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'youtube_requests',
                'tiktok_requests',
                'instagram_requests',
                'facebook_requests',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('youtube_requests')->default(0);
            $table->integer('tiktok_requests')->default(0);
            $table->integer('instagram_requests')->default(0);
            $table->integer('facebook_requests')->default(0);
        });
    }
};
