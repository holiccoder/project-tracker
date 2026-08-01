<?php

namespace App\Filament\Widgets;

use App\Enums\IssueStatus;
use App\Enums\TaskStatus;
use App\Models\DevLog;
use App\Models\Issue;
use App\Models\Task;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectOverviewStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $pendingTasks = Task::where('status', TaskStatus::Pending)->count();
        $openIssues = Issue::whereIn('status', [
            IssueStatus::Open,
            IssueStatus::InProgress,
        ])->count();
        $weeklyHours = (float) DevLog::whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('hours_spent');

        return [
            Stat::make('待确认任务', $pendingTasks)
                ->description('等待开发者确认')
                ->color($pendingTasks > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-inbox'),
            Stat::make('进行中问题', $openIssues)
                ->description('待处理 / 处理中')
                ->color($openIssues > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('本周工时', number_format($weeklyHours, 1).' h')
                ->description('基于开发记录工时')
                ->icon('heroicon-o-clock'),
        ];
    }
}
