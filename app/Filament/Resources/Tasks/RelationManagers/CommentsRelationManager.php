<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = '讨论评论';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('发表评论')
                    ->required()
                    ->maxLength(10000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('author.name')
                    ->label('作者')
                    ->badge()
                    ->color(fn ($record) => $record->author_type === \App\Models\Admin::class ? 'info' : 'success'),
                TextColumn::make('body')
                    ->label('内容')
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth('admin')->id();
                        $data['author_type'] = \App\Models\Admin::class;
                        return $data;
                    })
                    ->after(function (\App\Models\Comment $record) {
                        $task = $record->commentable;
                        $project = $task->project;

                        // Notify other admins
                        $admins = \App\Models\Admin::where('id', '!=', auth('admin')->id())->get();
                        if ($admins->isNotEmpty()) {
                            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewCommentNotification($record, $task));
                        }

                        // Notify all project members/clients
                        $members = $project->members;
                        if ($members->isNotEmpty()) {
                            \Illuminate\Support\Facades\Notification::send($members, new \App\Notifications\NewCommentNotification($record, $task));
                        }
                    }),
            ])
            ->actions([
                DeleteAction::make()->modalHeading('删除评论'),
            ]);
    }
}
