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
                    ->label('项目名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (ProjectStatus $state): string => $state->label())
                    ->color(fn (ProjectStatus $state): string => match ($state) {
                        ProjectStatus::Active => 'success',
                        ProjectStatus::Delivered => 'info',
                        ProjectStatus::Paused => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('项目总额')
                    ->numeric(2, '.', ',')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('paid_amount')
                    ->label('已付金额')
                    ->numeric(2, '.', ',')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('unpaid_amount')
                    ->label('未付金额')
                    ->state(fn (Project $record): ?string => $record->unpaid_amount)
                    ->numeric(2, '.', ',')
                    ->color(fn (Project $record): string => $record->unpaid_amount !== null && (float) $record->unpaid_amount > 0 ? 'warning' : 'success'),
                TextColumn::make('deadline')
                    ->label('截止日期')
                    ->date()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('members_count')
                    ->label('客户数')
                    ->counts('members'),
                TextColumn::make('remark')
                    ->label('备注')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('项目状态')
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
