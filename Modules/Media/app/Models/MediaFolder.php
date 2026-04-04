<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MediaFolder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (MediaFolder $folder) {
            if (empty($folder->slug)) {
                $folder->slug = Str::slug($folder->name);
            }
        });
    }

    /**
     * Get the parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    /**
     * Get child folders.
     */
    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id');
    }

    /**
     * Get all descendants.
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get media in this folder.
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'folder', 'path');
    }

    /**
     * Get the full path of the folder.
     */
    public function getPath(): string
    {
        $segments = collect([$this->slug]);

        $parent = $this->parent;
        while ($parent) {
            $segments->prepend($parent->slug);
            $parent = $parent->parent;
        }

        return '/' . $segments->implode('/');
    }

    /**
     * Scope for root folders.
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }
}
