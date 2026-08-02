<?php

namespace App\Filament\Resources\Issues\Schemas;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class IssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                TextInput::make('title')
                    ->label('问题标题')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('描述/详情')
                    ->rows(3)
                    ->columnSpanFull(),
                FileUpload::make('attachment_path')
                    ->label('附件/截图')
                    ->disk('local')
                    ->directory('issue-attachments')
                    ->maxSize(10240) // 10MB
                    ->acceptedFileTypes(['image/*', 'application/pdf', 'application/zip', 'application/x-zip-compressed', 'text/plain'])
                    ->columnSpanFull(),
                Select::make('severity')
                    ->label('严重程度')
                    ->options(IssueSeverity::class)
                    ->default(IssueSeverity::Normal->value)
                    ->required(),
                Select::make('status')
                    ->label('状态')
                    ->options(IssueStatus::class)
                    ->default(IssueStatus::Open->value)
                    ->required(),
            ]);
    }
}
