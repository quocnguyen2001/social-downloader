<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasTable('subscriptions')) {
            Schema::rename('orders', 'subscriptions');
        }

        if (Schema::hasColumn('transactions', 'order_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('transactions_order_id_status_index');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('order_id', 'subscription_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['subscription_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'subscription_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('transactions_subscription_id_status_index');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->renameColumn('subscription_id', 'order_id');
            });

            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['order_id', 'status']);
            });
        }

        if (Schema::hasTable('subscriptions') && ! Schema::hasTable('orders')) {
            Schema::rename('subscriptions', 'orders');
        }
    }
};
