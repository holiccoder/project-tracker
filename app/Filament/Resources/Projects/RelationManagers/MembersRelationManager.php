<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Enums\ProjectUserRole;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
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
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('pivot.role')
                    ->label('角色')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProjectUserRole::tryFrom($state)?->label() ?? $state)
                    ->color(fn (string $state): string => $state === ProjectUserRole::Owner->value ? 'purple' : 'gray'),
                ToggleColumn::make('can_view_price')
                    ->label('可查看金额')
                    ->getStateUsing(fn ($record): bool => (bool) $record->pivot?->can_view_price),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select->searchable()->preload())
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('role')
                            ->label('角色')
                            ->options(ProjectUserRole::class)
                            ->default(ProjectUserRole::Member->value),
                        Toggle::make('can_view_price')
                            ->label('允许查看项目金额')
                            ->default(false),
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
