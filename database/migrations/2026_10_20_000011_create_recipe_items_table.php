<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->foreignId('menu_variant_id')->nullable()->constrained('menu_variants')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('sizes')->nullOnDelete();
            $table->foreignId('topping_id')->nullable()->constrained('toppings')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'menu_variant_id', 'size_id', 'is_active']);
            $table->index(['topping_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
