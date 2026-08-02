<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->columnSpanFull(),
                TextInput::make('title')
                    ->label('任务标题')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('任务描述')
                    ->rows(6)
                    ->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label('附件')
                    ->multiple()
                    ->disk('public')
                    ->directory('task-attachments')
                    ->maxFiles(10)
                    ->downloadable()
                    ->previewable(false)
                    ->columnSpanFull(),
                Select::make('priority')
                    ->label('优先级')
                    ->options(TaskPriority::class)
                    ->default(TaskPriority::Medium->value)
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('状态')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Pending->value)
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('reject_reason')
                    ->label('拒绝原因')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn (?Task $record): bool => $record?->status === TaskStatus::Rejected)
                    ->columnSpanFull(),
            ]);
    }
}
