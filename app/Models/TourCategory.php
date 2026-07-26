<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TourCategory extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'is_active', 'sort_order'];

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $cat) {
            if (empty($cat->slug)) {
                $cat->slug = Str::slug($cat->name);
            }
        });

        static::updating(function (self $cat) {
            if ($cat->isDirty('name') && ! $cat->isDirty('slug')) {
                $cat->slug = Str::slug($cat->name);
            }
        });
    }
}
