<?php

namespace App\Filament\Resources\Contracts\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class ContractForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('contracts', 'project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                FilamentInputFactory::make('contracts', 'name'),
                FilamentInputFactory::make('contracts', 'file_path'),
            ]);
    }
}
