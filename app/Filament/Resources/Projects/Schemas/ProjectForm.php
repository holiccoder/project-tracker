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
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('留空将根据项目名自动生成')
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(ProjectStatus::class)
                    ->default(ProjectStatus::Active->value)
                    ->required(),
                TextInput::make('amount')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('¥')
                    ->live(),
                TextInput::make('paid_amount')
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
                DatePicker::make('deadline'),
                TextInput::make('repo_url')
                    ->url()
                    ->placeholder('https://github.com/...')
                    ->columnSpanFull(),
            ]);
    }
}
