<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Comment;
use App\Models\Task;
use App\Support\CurrentAdmin;
use App\Support\InputSchemaRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class TaskCommentController extends Controller
{
    public function index(Task $task): JsonResponse
    {
        $comments = $task->comments()->with('author')->latest()->paginate(50);

        return response()->json([
            'data' => $comments->map(fn (Comment $comment): array => $this->toArray($comment))->values()->all(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $validated = validator($request->all(), InputSchemaRegistry::rules('task_comments', 'create'))->validate();
        $authorId = CurrentAdmin::requiredId($request, 'author_id');

        $comment = $task->comments()->create([
            'body' => $validated['body'],
            'author_id' => $authorId,
            'author_type' => Admin::class,
        ]);

        $admins = Admin::where('id', '!=', $authorId)->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new \App\Notifications\NewCommentNotification($comment, $task));
        }

        $members = $task->project?->members;
        if ($members !== null && $members->isNotEmpty()) {
            Notification::send($members, new \App\Notifications\NewCommentNotification($comment, $task));
        }

        return response()->json($this->toArray($comment->load('author')), 201);
    }

    public function destroy(Task $task, Comment $comment): JsonResponse
    {
        abort_unless($comment->commentable_type === Task::class && $comment->commentable_id === $task->id, 404);
        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    private function toArray(Comment $comment): array
    {
        return [
            'id' => $comment->id,
            'task_id' => $comment->commentable_id,
            'body' => $comment->body,
            'author' => $comment->author ? [
                'id' => $comment->author->id,
                'name' => $comment->author->name,
                'is_admin' => $comment->author_type === Admin::class,
            ] : null,
            'created_at' => $comment->created_at?->toISOString(),
            'updated_at' => $comment->updated_at?->toISOString(),
        ];
    }
}
