<?php

namespace App\Filament\Resources\Contracts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class ContractForm
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
                TextInput::make('name')
                    ->label('文件名')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('file_path')
                    ->label('合同文件')
                    ->disk('local')
                    ->directory('contracts')
                    ->preserveFilenames()
                    ->required(),
            ]);
    }
}
