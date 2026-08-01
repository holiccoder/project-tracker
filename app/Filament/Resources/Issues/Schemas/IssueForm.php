<?php

namespace App\Filament\Resources\Issues\Schemas;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class IssueForm
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
                Select::make('severity')
                    ->options(IssueSeverity::class)
                    ->default(IssueSeverity::Normal->value)
                    ->required(),
                Select::make('status')
                    ->options(IssueStatus::class)
                    ->default(IssueStatus::Open->value)
                    ->required(),
            ]);
    }
}
