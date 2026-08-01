<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('姓名')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('邮箱')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                Textarea::make('remark')
                    ->label('备注(仅后台可见)')
                    ->rows(2)
                    ->helperText('给客户起的备注名,客户前端不可见')
                    ->columnSpanFull(),
                TextInput::make('password')
                    ->label('初始密码')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('创建时必填,编辑时留空则不修改'),
            ]);
    }
}
