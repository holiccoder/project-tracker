<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('任务标题')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('项目')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('优先级')
                    ->badge()
                    ->formatStateUsing(fn (TaskPriority $state): string => $state->label())
                    ->color(fn (TaskPriority $state): string => match ($state) {
                        TaskPriority::Low => 'gray',
                        TaskPriority::Medium => 'info',
                        TaskPriority::High => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (TaskStatus $state): string => $state->label())
                    ->color(fn (TaskStatus $state): string => match ($state) {
                        TaskStatus::Pending => 'gray',
                        TaskStatus::Confirmed => 'info',
                        TaskStatus::InProgress => 'warning',
                        TaskStatus::Done => 'primary',
                        TaskStatus::Accepted => 'success',
                        TaskStatus::Rejected => 'danger',
                        TaskStatus::ChangesRequested => 'warning',
                    })
                    ->sortable(),
                TextColumn::make('latest_comment')
                    ->label('最近评论')
                    ->placeholder('—')
                    ->formatStateUsing(function (Task $record): ?string {
                        $comment = $record->comments->sortByDesc('created_at')->first();

                        if (! $comment) {
                            return null;
                        }

                        $author = $comment->author?->name ?? '已注销用户';

                        return $author.': '.mb_strimwidth($comment->body, 0, 60, '…');
                    })
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('创建时间')
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
                SelectFilter::make('status')
                    ->label('任务状态')
                    ->options(TaskStatus::class),
                SelectFilter::make('priority')
                    ->label('优先级')
                    ->options(TaskPriority::class),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('确认')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => $record->transitionTo(TaskStatus::Confirmed))
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::Pending),
                Action::make('reject')
                    ->label('拒绝')
                    ->color('danger')
                    ->form([
                        Textarea::make('reject_reason')
                            ->label('拒绝原因')
                            ->required(),
                    ])
                    ->action(fn (Task $record, array $data) => $record->transitionTo(TaskStatus::Rejected, $data['reject_reason']))
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::Pending),
                Action::make('start')
                    ->label('开始处理')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => $record->transitionTo(TaskStatus::InProgress))
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::Confirmed),
                Action::make('restart')
                    ->label('返工处理')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => $record->transitionTo(TaskStatus::InProgress))
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::ChangesRequested),
                Action::make('complete')
                    ->label('标记完成')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn (Task $record) => $record->transitionTo(TaskStatus::Done))
                    ->visible(fn (Task $record): bool => $record->status === TaskStatus::InProgress),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with(['comments.author']));
    }
}
