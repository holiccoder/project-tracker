<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Users\UserResource;
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

class MembersRelationManager extends RelationManager
{
    protected static string $relationship = 'members';

    protected static ?string $relatedResource = UserResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('pivot.role')->label(InputSchemaRegistry::field('project_members', 'role')['label'])->badge(),
                ToggleColumn::make('can_view_price')
                    ->label(InputSchemaRegistry::field('project_members', 'can_view_price')['label'])
                    ->getStateUsing(fn ($record): bool => (bool) $record->pivot?->can_view_price),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select->label(InputSchemaRegistry::field('project_members', 'user_id')['label'])->searchable()->preload())
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect()->label(InputSchemaRegistry::field('project_members', 'user_id')['label']),
                        FilamentInputFactory::make('project_members', 'role'),
                        FilamentInputFactory::make('project_members', 'can_view_price'),
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
