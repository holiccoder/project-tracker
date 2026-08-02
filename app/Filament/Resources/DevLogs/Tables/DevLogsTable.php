<?php

namespace App\Filament\Resources\DevLogs\Tables;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DevLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('project.name')
                    ->label('项目')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (DevLogStatus $state): string => $state->label())
                    ->color(fn (DevLogStatus $state): string => match ($state) {
                        DevLogStatus::InProgress => 'warning',
                        DevLogStatus::Completed => 'success',
                    }),
                TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->formatStateUsing(fn (DevLogCategory $state): string => $state->label())
                    ->color(fn (DevLogCategory $state): string => match ($state) {
                        DevLogCategory::AgentIndependent => 'info',
                        DevLogCategory::HumanAgentCollaboration => 'primary',
                    }),
                TextColumn::make('date')
                    ->label('日期')
                    ->date()
                    ->sortable(),
                TextColumn::make('content')
                    ->label('内容')
                    ->limit(50),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(DevLogStatus::class),
                SelectFilter::make('category')
                    ->label('分类')
                    ->options(DevLogCategory::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
