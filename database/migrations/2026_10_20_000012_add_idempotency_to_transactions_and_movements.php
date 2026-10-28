<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable()->unique()->after('transaction_number');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('user_id')->constrained('transactions')->nullOnDelete();
            $table->string('reference_key', 180)->nullable()->unique()->after('transaction_id');
            $table->index(['transaction_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropUnique(['reference_key']);
            $table->dropIndex(['transaction_id', 'ingredient_id']);
            $table->dropColumn(['transaction_id', 'reference_key']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
