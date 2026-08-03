<?php

namespace App\Filament\Resources\DevLogUpdates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DevLogUpdatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('devLog.project.name')
                    ->label('项目')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('devLog.date')
                    ->label('日志日期')
                    ->date()
                    ->sortable(),
                TextColumn::make('devLog.content')
                    ->label('开发日志')
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('update')
                    ->label('更新内容')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('dev_log_id')
                    ->label('开发日志')
                    ->relationship('devLog', 'content')
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
