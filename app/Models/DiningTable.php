<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiningTable extends Model
{
    protected $fillable = ['branch_id', 'name', 'capacity', 'is_active'];

    protected $casts = ['capacity' => 'integer', 'is_active' => 'boolean'];

    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function transactions(): HasMany { return $this->hasMany(Transaction::class, 'dining_table_id'); }
}
