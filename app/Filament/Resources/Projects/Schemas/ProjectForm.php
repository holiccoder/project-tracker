<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('项目名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->label('项目标识')
                    ->helperText('留空将根据项目名自动生成')
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('项目描述')
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('members')
                    ->label('分配客户')
                    ->relationship('members', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('项目状态')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Active->value)
                    ->required(),
                TextInput::make('amount')
                    ->label('项目总额')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('¥')
                    ->live(),
                TextInput::make('paid_amount')
                    ->label('已付金额')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('¥')
                    ->live(),
                Placeholder::make('unpaid_amount')
                    ->label('未付金额')
                    ->content(fn (Get $get): ?string => $get('amount') !== null
                        ? number_format((float) $get('amount') - (float) ($get('paid_amount') ?? 0), 2, '.', '')
                        : null),
                DatePicker::make('deadline')
                    ->label('截止日期'),
                TextInput::make('repo_url')
                    ->label('代码仓库')
                    ->url()
                    ->placeholder('https://github.com/...')
                    ->columnSpanFull(),
                Textarea::make('remark')
                    ->label('备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
