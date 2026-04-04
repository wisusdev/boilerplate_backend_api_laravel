<?php

namespace Modules\Blog\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Blog\Models\Comment;
use Modules\Blog\Models\Post;

class CommentController extends Controller
{
    /**
     * Display a listing of comments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Comment::with(['post:id,title,slug', 'user:id,first_name,last_name']);

        if ($request->has('post_id')) {
            $query->where('post_id', $request->post_id);
        }

        if ($request->has('is_approved')) {
            $query->where('is_approved', $request->boolean('is_approved'));
        }

        $comments = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($comments);
    }

    /**
     * Store a new comment (public endpoint).
     */
    public function store(Request $request, Post $post): JsonResponse
    {
        if (!$post->allow_comments) {
            return response()->json(['message' => 'Comments are disabled for this post.'], 403);
        }

        $validated = $request->validate([
            'author_name' => 'required_without:user_id|string|max:255',
            'author_email' => 'required_without:user_id|email|max:255',
            'content' => 'required|string|max:2000',
            'parent_id' => 'nullable|uuid|exists:comments,id',
        ]);

        $validated['post_id'] = $post->id;
        $validated['user_id'] = auth()->id();
        $validated['is_approved'] = false; // Requires moderation by default

        $comment = Comment::create($validated);

        return response()->json([
            'message' => 'Comment submitted successfully. It will be visible after approval.',
            'data' => $comment,
        ], 201);
    }

    /**
     * Display the specified comment.
     */
    public function show(Comment $comment): JsonResponse
    {
        $comment->load(['post:id,title,slug', 'user:id,first_name,last_name', 'replies']);

        return response()->json(['data' => $comment]);
    }

    /**
     * Update the specified comment.
     */
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'sometimes|required|string|max:2000',
            'is_approved' => 'sometimes|boolean',
        ]);

        $comment->update($validated);

        return response()->json(['data' => $comment]);
    }

    /**
     * Remove the specified comment.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $comment->replies()->delete();
        $comment->delete();

        return response()->json(null, 204);
    }

    /**
     * Approve a comment.
     */
    public function approve(Comment $comment): JsonResponse
    {
        $comment->update(['is_approved' => true]);

        return response()->json([
            'message' => 'Comment approved successfully.',
            'data' => $comment,
        ]);
    }

    /**
     * Reject a comment.
     */
    public function reject(Comment $comment): JsonResponse
    {
        $comment->update(['is_approved' => false]);

        return response()->json([
            'message' => 'Comment rejected.',
            'data' => $comment,
        ]);
    }
}
