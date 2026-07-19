<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reviewable_id',
        'reviewable_type',
        'rating',
        'comment',
        'is_approved',
        'admin_reply',
        'replied_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
        'replied_at' => 'datetime',
    ];

    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Scopes de filtrado (allowedFilters) ──────────────────────────────────
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeIsApproved($query, $value)
    {
        return $query->where('is_approved', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    public function scopeReviewableType($query, $value)
    {
        $class = Booking::bookableClassFor($value) ?? $value;

        return $query->where('reviewable_type', $class);
    }

    public function scopeReviewableId($query, $value)
    {
        return $query->where('reviewable_id', $value);
    }

    public function getResourceType(): string
    {
        return 'product_reviews';
    }
}
