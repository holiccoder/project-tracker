<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;

        // Authorization: must be member of project
        $user = auth('web')->user();

        if (! $user || ! $project->hasMember($user)) {
            abort(403, '你没有权限为此任务发表评论。');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachmentPaths = [];

        if (! empty($validated['attachments'])) {
            foreach ($validated['attachments'] as $file) {
                $attachmentPaths[] = $file->store('comment-attachments', 'public');
            }
        }

        $comment = $task->comments()->create([
            'body' => $validated['body'],
            'attachments' => $attachmentPaths ?: null,
            'author_id' => $user->id,
            'author_type' => get_class($user),
        ]);

        // Notify all admins
        $admins = \App\Models\Admin::all();
        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewCommentNotification($comment, $task));

        // Notify other project members/clients
        $membersToNotify = $project->members->where('id', '!=', $user->id);
        if ($membersToNotify->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($membersToNotify, new \App\Notifications\NewCommentNotification($comment, $task));
        }

        return back()->with('success', '评论发表成功。');
    }
}
