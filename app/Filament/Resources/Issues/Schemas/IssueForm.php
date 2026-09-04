<?php

namespace App\Filament\Resources\Issues\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class IssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('issues', 'project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                FilamentInputFactory::make('issues', 'title'),
                FilamentInputFactory::make('issues', 'description')->columnSpanFull(),
                FilamentInputFactory::make('issues', 'attachment_path')->columnSpanFull(),
                FilamentInputFactory::make('issues', 'severity'),
                FilamentInputFactory::make('issues', 'status'),
            ]);
    }
}
