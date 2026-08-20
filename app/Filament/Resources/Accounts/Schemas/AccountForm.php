<?php

namespace App\Filament\Resources\Accounts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->label('所属项目')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('website_name')
                    ->label('网站名称')
                    ->required()
                    ->maxLength(255),
                TextInput::make('login_url')
                    ->label('登录地址')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->placeholder('https://example.com/login')
                    ->columnSpanFull(),
                TextInput::make('username')
                    ->label('用户名')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->label('密码')
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
                Textarea::make('note')
                    ->label('备注')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
