<?php

namespace App\Filament\Resources\DevLogs\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class DevLogForm
{
    public static function entryFields(): array
    {
        return [
            FilamentInputFactory::make('dev_logs', 'date'),
            FilamentInputFactory::make('dev_logs', 'status'),
            FilamentInputFactory::make('dev_logs', 'category'),
            FilamentInputFactory::make('dev_logs', 'content')->columnSpanFull(),
        ];
    }

    public static function batchRepeater(): Repeater
    {
        return Repeater::make('logs')
            ->label('Development logs')
            ->schema(self::entryFields())
            ->columns(3)
            ->defaultItems(1)
            ->addActionLabel('Add log')
            ->itemLabel(fn (array $state): ?string => $state['date'] ?? null);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('dev_logs', 'project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                ...self::entryFields(),
            ]);
    }
}
