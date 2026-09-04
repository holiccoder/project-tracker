<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Support\FilamentInputFactory;
use App\Support\InputSchemaRegistry;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('projects', 'name'),
                FilamentInputFactory::make('projects', 'slug'),
                FilamentInputFactory::make('projects', 'description')->columnSpanFull(),
                FilamentInputFactory::make('projects', 'members')
                    ->relationship('members', 'name')
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                FilamentInputFactory::make('projects', 'status'),
                FilamentInputFactory::make('projects', 'amount')->live(),
                FilamentInputFactory::make('projects', 'paid_amount')->live(),
                Placeholder::make('unpaid_amount')
                    ->label(InputSchemaRegistry::field('projects', 'unpaid_amount')['label'])
                    ->content(fn (Get $get): ?string => $get('amount') !== null
                        ? number_format((float) $get('amount') - (float) ($get('paid_amount') ?? 0), 2, '.', '')
                        : null),
                FilamentInputFactory::make('projects', 'deadline'),
                FilamentInputFactory::make('projects', 'repo_url')->columnSpanFull(),
                FilamentInputFactory::make('projects', 'remark')->columnSpanFull(),
            ]);
    }
}
