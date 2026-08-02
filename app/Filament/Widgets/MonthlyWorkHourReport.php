<?php

namespace App\Filament\Widgets;

use App\Models\DevLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class MonthlyWorkHourReport extends TableWidget
{
    protected function getTableHeading(): string
    {
        return '开发记录月度报表';
    }

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        // Use a subquery to completely bypass MySQL only_full_group_by when Filament appends dev_logs.id to ORDER BY
        $subQuery = DevLog::query()
            ->select('project_id')
            ->selectRaw('MIN(id) as id')
            ->selectRaw('SUBSTR(date, 1, 7) as month')
            ->selectRaw('COUNT(*) as total_logs')
            ->groupBy('project_id', 'month')
            ->orderBy('month', 'desc');

        $query = DevLog::query()
            ->fromSub($subQuery, 'dev_logs')
            ->with('project');

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('project.name')
                    ->label('项目名称'),
                TextColumn::make('month')
                    ->label('月份'),
                TextColumn::make('total_logs')
                    ->label('日志记录数')
                    ->suffix(' 条')
                    ->placeholder('0'),
            ])
            ->paginated(false);
    }
}
