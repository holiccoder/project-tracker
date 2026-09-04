<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use App\Support\FilamentInputFactory;
use App\Support\InputSchemaRegistry;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    protected static ?string $relatedResource = ProjectResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('pivot.role')->label(InputSchemaRegistry::field('user_projects', 'role')['label'])->badge(),
                ToggleColumn::make('can_view_price')
                    ->label(InputSchemaRegistry::field('user_projects', 'can_view_price')['label'])
                    ->getStateUsing(fn ($record): bool => (bool) $record->pivot?->can_view_price),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select->label(InputSchemaRegistry::field('user_projects', 'project_id')['label'])->searchable()->preload())
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label(InputSchemaRegistry::field('user_projects', 'project_id')['label']),
                        FilamentInputFactory::make('user_projects', 'role'),
                        FilamentInputFactory::make('user_projects', 'can_view_price'),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
