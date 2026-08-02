<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, Task $task): RedirectResponse
    {
        $project = $task->project;
        
        // Authorization: must be member of project
        $user = auth('web')->user();
        
        if (!$user || !$project->hasMember($user)) {
            abort(403, '你没有权限为此任务发表评论。');
        }

        $request->validate([
            'body' => 'required|string|max:10000',
        ]);

        $comment = $task->comments()->create([
            'body' => $request->body,
            'author_id' => $user->id,
            'author_type' => get_class($user),
        ]);

        $admins = \App\Models\Admin::all();
        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewCommentNotification($comment, $task));

        return back()->with('success', '评论发表成功。');
    }
}
