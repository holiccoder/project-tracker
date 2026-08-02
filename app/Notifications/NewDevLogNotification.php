<?php

namespace App\Notifications;

use App\Models\DevLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDevLogNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public DevLog $devLog) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("【项目追踪】新开发记录：{$this->devLog->project->name}")
            ->line("开发者提交了新的开发记录：")
            ->line($this->devLog->content)
            ->action('查看项目', url("/projects/{$this->devLog->project->slug}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'dev_log_id' => $this->devLog->id,
            'project_name' => $this->devLog->project->name,
            'message' => "新开发记录提交：{$this->devLog->content}",
        ];
    }
}
