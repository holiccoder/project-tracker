<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskStatusChangedNotification extends Notification implements ShouldQueue
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
            ->subject("【项目追踪】任务状态更新：{$this->task->title}")
            ->line("您委派的任务状态已更新为：{$this->task->status->label()}")
            ->action('查看任务', url("/projects/{$this->task->project->slug}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'task_id' => $this->task->id,
            'title' => $this->task->title,
            'status' => $this->task->status->value,
            'project_name' => $this->task->project->name,
            'message' => "任务状态更新为：{$this->task->status->label()}",
        ];
    }
}
