<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menus') && ! Schema::hasColumn('menus', 'uses_recipe')) {
            Schema::table('menus', function (Blueprint $table) {
                $table->boolean('uses_recipe')->default(false);
            });
        }
        if (Schema::hasTable('toppings') && ! Schema::hasColumn('toppings', 'uses_recipe')) {
            Schema::table('toppings', function (Blueprint $table) {
                $table->boolean('uses_recipe')->default(false);
            });
        }

        // Preserve current stock deduction for products that already have recipes;
        // new products opt in explicitly through their master-data form.
        if (Schema::hasColumn('menus', 'uses_recipe') && Schema::hasTable('recipe_items')) {
            DB::table('menus')->whereExists(function ($query) {
                $query->select(DB::raw(1))->from('recipe_items')
                    ->whereColumn('recipe_items.menu_id', 'menus.id')
                    ->whereNull('recipe_items.topping_id')->where('recipe_items.is_active', true);
            })->update(['uses_recipe' => true]);
        }
        if (Schema::hasColumn('toppings', 'uses_recipe') && Schema::hasTable('recipe_items')) {
            DB::table('toppings')->whereExists(function ($query) {
                $query->select(DB::raw(1))->from('recipe_items')
                    ->whereColumn('recipe_items.topping_id', 'toppings.id')
                    ->whereNull('recipe_items.menu_id')->where('recipe_items.is_active', true);
            })->update(['uses_recipe' => true]);
        }
    }

    public function down(): void
    {
        // Rollback removes recipe opt-in settings. Back up first if they were edited
        // after this migration; no recipe, stock, or movement rows are removed.
        foreach (['menus', 'toppings'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'uses_recipe')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('uses_recipe');
                });
            }
        }
    }
};
