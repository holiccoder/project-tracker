<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Models\Admin;
use App\Support\FilamentInputFactory;
use App\Support\InputSchemaRegistry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('task_comments', 'body')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('author.name')->label('Author')->badge(),
                TextColumn::make('body')->label(InputSchemaRegistry::field('task_comments', 'body')['label'])->wrap(),
                TextColumn::make('created_at')->label('Created at')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['author_id'] = auth('admin')->id();
                        $data['author_type'] = Admin::class;
                        return $data;
                    })
                    ->after(function (\App\Models\Comment $record): void {
                        $task = $record->commentable;
                        $project = $task->project;
                        $admins = Admin::where('id', '!=', auth('admin')->id())->get();
                        if ($admins->isNotEmpty()) {
                            \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\NewCommentNotification($record, $task));
                        }
                        $members = $project->members;
                        if ($members->isNotEmpty()) {
                            \Illuminate\Support\Facades\Notification::send($members, new \App\Notifications\NewCommentNotification($record, $task));
                        }
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
