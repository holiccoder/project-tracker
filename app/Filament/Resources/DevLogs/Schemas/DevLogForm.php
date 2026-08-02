<?php

namespace App\Filament\Resources\DevLogs\Schemas;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;

class DevLogForm
{
    public static function entryFields(): array
    {
        return [
            DatePicker::make('date')
                ->label('日期')
                ->default(now())
                ->required(),
            Select::make('status')
                ->label('状态')
                ->options(DevLogStatus::class)
                ->default(DevLogStatus::InProgress->value)
                ->required(),
            Select::make('category')
                ->label('分类')
                ->options(DevLogCategory::class)
                ->default(DevLogCategory::AgentIndependent->value)
                ->required(),
            Textarea::make('content')
                ->label('记录内容')
                ->rows(4)
                ->required()
                ->columnSpanFull(),
        ];
    }

    public static function batchRepeater(): Repeater
    {
        return Repeater::make('logs')
            ->label('开发日志')
            ->schema(self::entryFields())
            ->columns(3)
            ->defaultItems(1)
            ->addActionLabel('添加一条日志')
            ->itemLabel(fn (array $state): ?string => $state['date'] ?? null);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager)
                    ->visible(fn (Select $component): bool => ! $component->getLivewire() instanceof RelationManager),
                ...self::entryFields(),
            ]);
    }
}
