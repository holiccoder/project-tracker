<?php

namespace App\Filament\Resources\DevLogs\Schemas;

use App\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DevLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                Select::make('task_id')
                    ->label('关联任务')
                    ->options(fn (Get $get): array => Task::query()
                        ->when($get('project_id'), fn ($query, $projectId) => $query->where('project_id', $projectId))
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable(),
                DatePicker::make('date')
                    ->default(now())
                    ->required(),
                Textarea::make('content')
                    ->label('记录内容')
                    ->rows(4)
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('hours_spent')
                    ->label('工时(小时)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(24)
                    ->step(0.1)
                    ->placeholder('可选'),
            ]);
    }
}
