<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'menu_tag')->withTimestamps();
    }
}
