<?php

namespace App\Filament\Resources\Tasks\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $task = $this->getRecord();
        $project = $task->project;

        // Notify other admins
        $admins = \App\Models\Admin::where('id', '!=', auth('admin')->id())->get();
        if ($admins->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\TaskDelegatedNotification($task));
        }

        // Notify project members/clients
        $members = $project->members;
        if ($members->isNotEmpty()) {
            \Illuminate\Support\Facades\Notification::send($members, new \App\Notifications\TaskDelegatedNotification($task));
        }
    }
}
