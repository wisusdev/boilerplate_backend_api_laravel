<?php

namespace Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'author_name',
        'author_email',
        'content',
        'status',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the post this comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the user who made this comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the child comments (replies).
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
            ->where('status', 'approved')
            ->orderBy('created_at');
    }

    /**
     * Scope for approved comments.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending comments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for top-level comments (no parent).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get the author name (from user or guest).
     */
    public function getAuthorName(): string
    {
        if ($this->user) {
            return $this->user->first_name . ' ' . $this->user->last_name;
        }

        return $this->author_name ?? 'Anonymous';
    }

    /**
     * Get the author avatar.
     */
    public function getAuthorAvatar(): ?string
    {
        if ($this->user && $this->user->avatar) {
            return $this->user->avatar;
        }

        // Gravatar fallback
        $email = $this->user?->email ?? $this->author_email;
        if ($email) {
            return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=mp';
        }

        return null;
    }

    /**
     * Approve the comment.
     */
    public function approve(): bool
    {
        return $this->update(['status' => 'approved']);
    }

    /**
     * Mark the comment as spam.
     */
    public function markAsSpam(): bool
    {
        return $this->update(['status' => 'spam']);
    }

    /**
     * Trash the comment.
     */
    public function trash(): bool
    {
        return $this->update(['status' => 'trash']);
    }
}
