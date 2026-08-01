<?php

namespace App\Filament\Resources\Issues\Tables;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use App\Models\Issue;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IssuesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('项目')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('severity')
                    ->badge()
                    ->formatStateUsing(fn (IssueSeverity $state): string => $state->label())
                    ->color(fn (IssueSeverity $state): string => match ($state) {
                        IssueSeverity::Normal => 'gray',
                        IssueSeverity::Serious => 'warning',
                        IssueSeverity::Blocking => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (IssueStatus $state): string => $state->label())
                    ->color(fn (IssueStatus $state): string => match ($state) {
                        IssueStatus::Open => 'danger',
                        IssueStatus::InProgress => 'warning',
                        IssueStatus::Resolved => 'success',
                        IssueStatus::Closed => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('severity')
                    ->options(IssueSeverity::class),
                SelectFilter::make('status')
                    ->options(IssueStatus::class),
            ])
            ->recordActions([
                Action::make('start')
                    ->label('开始处理')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Issue $record) => $record->transitionTo(IssueStatus::InProgress))
                    ->visible(fn (Issue $record): bool => $record->status === IssueStatus::Open),
                Action::make('resolve')
                    ->label('标记解决')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Issue $record) => $record->transitionTo(IssueStatus::Resolved))
                    ->visible(fn (Issue $record): bool => $record->status === IssueStatus::InProgress),
                Action::make('close')
                    ->label('关闭')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Issue $record) => $record->transitionTo(IssueStatus::Closed))
                    ->visible(fn (Issue $record): bool => $record->status === IssueStatus::Resolved),
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
