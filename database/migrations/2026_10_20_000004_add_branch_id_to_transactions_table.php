<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('cashier_id')
                    ->constrained('branches')
                    ->nullOnDelete();
                $table->index(['branch_id', 'sold_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'branch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropIndex(['branch_id', 'sold_at']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
