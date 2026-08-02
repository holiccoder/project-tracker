<?php

namespace App\Notifications;

use App\Models\Issue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Issue $issue) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("【项目追踪】问题状态更新：{$this->issue->title}")
            ->line("项目 {$this->issue->project->name} 中的问题状态已更新为：{$this->issue->status->label()}")
            ->action('查看项目', url("/projects/{$this->issue->project->slug}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'issue_id' => $this->issue->id,
            'title' => $this->issue->title,
            'status' => $this->issue->status->value,
            'project_name' => $this->issue->project->name,
            'message' => "问题状态更新为：{$this->issue->status->label()}",
        ];
    }
}
