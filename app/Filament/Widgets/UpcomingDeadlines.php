<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingDeadlines extends TableWidget
{
    protected function getTableHeading(): string
    {
        return '即将到期的任务';
    }

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Task::query()
                    ->whereNotNull('due_date')
                    ->whereNotIn('status', [
                        TaskStatus::Accepted,
                        TaskStatus::Rejected,
                    ])
                    ->whereBetween('due_date', [now()->startOfDay(), now()->addDays(14)->endOfDay()])
                    ->orderBy('due_date'),
            )
            ->columns([
                TextColumn::make('title')
                    ->limit(40),
                TextColumn::make('project.name')
                    ->label('项目'),
                TextColumn::make('due_date')
                    ->label('截止日期')
                    ->date()
                    ->color(fn (Task $record): string => $record->due_date->isPast() ? 'danger' : 'warning'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus $state): string => $state->label()),
            ])
            ->paginated(false);
    }
}
