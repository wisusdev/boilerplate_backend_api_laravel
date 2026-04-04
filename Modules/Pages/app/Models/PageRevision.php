<?php

namespace Modules\Pages\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    protected $fillable = [
        'page_id',
        'user_id',
        'title',
        'content',
        'content_html',
        'content_json',
        'content_css',
        'meta',
    ];

    protected $casts = [
        'content_json' => 'array',
        'content_css' => 'array',
        'meta' => 'array',
    ];

    /**
     * Get the page this revision belongs to.
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the user who created this revision.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
