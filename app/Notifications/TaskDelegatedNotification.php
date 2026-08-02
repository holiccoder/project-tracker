<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDelegatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("【项目追踪】新任务委派：{$this->task->title}")
            ->line("客户委派了新任务：{$this->task->title}")
            ->line("描述：{$this->task->description}")
            ->line("优先级：{$this->task->priority->value}")
            ->action('查看任务', url("/admin/tasks/{$this->task->id}/edit"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'project_name' => $this->task->project->name,
            'message' => "新任务委派：{$this->task->title}",
        ];
    }
}
