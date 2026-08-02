<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Comment $comment, public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $notifiable instanceof \App\Models\Admin
            ? url("/admin/tasks/{$this->task->id}/edit")
            : url("/projects/{$this->task->project->slug}/tasks/{$this->task->id}");

        return (new MailMessage)
            ->subject("【项目追踪】任务新评论：{$this->task->title}")
            ->line("您的任务有了新的评论：")
            ->line($this->comment->body)
            ->action('查看任务', $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'comment_id' => $this->comment->id,
            'task_id' => $this->task->id,
            'message' => "新任务评论：{$this->comment->body}",
        ];
    }
}
