<?php

namespace Modules\Blog\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Models\Comment;

class CommentController extends Controller
{
    /**
     * Display a listing of comments.
     */
    public function index(Request $request): View
    {
        $query = Comment::with(['post', 'user', 'parent']);

        if ($request->has('status')) {
            if ($request->status === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->status === 'approved') {
                $query->where('is_approved', true);
            }
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('blog::admin.comments.index', compact('comments'));
    }

    /**
     * Approve a comment.
     */
    public function approve(Comment $comment): RedirectResponse
    {
        $comment->update(['is_approved' => true]);

        return redirect()
            ->back()
            ->with('success', __('Comment approved successfully.'));
    }

    /**
     * Reject a comment.
     */
    public function reject(Comment $comment): RedirectResponse
    {
        $comment->update(['is_approved' => false]);

        return redirect()
            ->back()
            ->with('success', __('Comment rejected.'));
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Comment $comment): RedirectResponse
    {
        // Also delete all replies
        $comment->replies()->delete();
        $comment->delete();

        return redirect()
            ->back()
            ->with('success', __('Comment deleted successfully.'));
    }
}
