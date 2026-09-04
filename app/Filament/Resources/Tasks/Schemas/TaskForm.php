<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Models\Task;
use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                FilamentInputFactory::make('tasks', 'project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'title')->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'description')->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'attachments')
                    ->downloadable()
                    ->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'priority')->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'status')->columnSpanFull(),
                FilamentInputFactory::make('tasks', 'reject_reason')
                    ->disabled()
                    ->visible(fn (?Task $record): bool => $record?->status?->value === 'rejected')
                    ->columnSpanFull(),
            ]);
    }
}
