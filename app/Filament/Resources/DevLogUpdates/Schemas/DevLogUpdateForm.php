<?php

namespace App\Filament\Resources\DevLogUpdates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class DevLogUpdateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dev_log_id')
                    ->label('开发日志')
                    ->relationship('devLog', 'content')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => sprintf(
                        '%s - %s',
                        $record->project?->name ?? '未命名项目',
                        str($record->content)->limit(60),
                    ))
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('update')
                    ->label('更新内容')
                    ->rows(6)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
