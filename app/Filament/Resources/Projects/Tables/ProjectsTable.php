<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->label())
                    ->color(fn (ProjectStatus $state): string => match ($state) {
                        ProjectStatus::Active => 'success',
                        ProjectStatus::Delivered => 'info',
                        ProjectStatus::Paused => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->numeric(2, '.', ',')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_amount')
                    ->numeric(2, '.', ',')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('unpaid_amount')
                    ->label('未付金额')
                    ->state(fn (Project $record): ?string => $record->unpaid_amount)
                    ->numeric(2, '.', ',')
                    ->color(fn (Project $record): string => $record->unpaid_amount !== null && (float) $record->unpaid_amount > 0 ? 'warning' : 'success'),
                TextColumn::make('deadline')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->label('客户数')
                    ->counts('members'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ProjectStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
