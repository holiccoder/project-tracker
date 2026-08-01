<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Forms\Components\DatePicker;
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
            ->components([
                Select::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('priority')
                    ->options(TaskPriority::class)
                    ->default(TaskPriority::Medium->value)
                    ->required(),
                Select::make('status')
                    ->options(TaskStatus::class)
                    ->default(TaskStatus::Pending->value)
                    ->required(),
                DatePicker::make('due_date'),
                Textarea::make('reject_reason')
                    ->label('拒绝原因')
                    ->rows(2)
                    ->disabled()
                    ->visible(fn (Task $record): bool => $record?->status === TaskStatus::Rejected),
            ]);
    }
}
