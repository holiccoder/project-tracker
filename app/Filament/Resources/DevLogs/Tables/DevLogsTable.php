<?php

namespace App\Filament\Resources\DevLogs\Tables;

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
                TextColumn::make('task.title')
                    ->label('关联任务')
                    ->placeholder('—')
                    ->limit(30),
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('content')
                    ->limit(50),
                TextColumn::make('hours_spent')
                    ->label('工时')
                    ->suffix(' h')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
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
