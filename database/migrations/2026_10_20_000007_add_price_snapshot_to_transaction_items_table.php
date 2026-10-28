<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->string('menu_name')->nullable()->after('menu_id');
            $table->decimal('menu_price', 10, 2)->nullable()->after('unit_price');
            $table->decimal('branch_price', 10, 2)->nullable()->after('menu_price');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['menu_name', 'menu_price', 'branch_price']);
        });
    }
};
