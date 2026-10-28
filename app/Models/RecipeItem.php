<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id', 'menu_variant_id', 'size_id', 'topping_id', 'ingredient_id',
        'quantity', 'unit', 'is_active',
    ];

    protected $casts = ['quantity' => 'decimal:3', 'is_active' => 'boolean'];

    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
    public function variant(): BelongsTo { return $this->belongsTo(MenuVariant::class, 'menu_variant_id'); }
    public function size(): BelongsTo { return $this->belongsTo(Size::class); }
    public function topping(): BelongsTo { return $this->belongsTo(Topping::class); }
    public function ingredient(): BelongsTo { return $this->belongsTo(Ingredient::class); }
}
