<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('姓名')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable(),
                TextColumn::make('wechat')
                    ->label('微信')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('phone')
                    ->label('手机号码')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('remark')
                    ->label('备注')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('projects_count')
                    ->label('项目数')
                    ->counts('projects'),
                TextColumn::make('created_at')
                    ->label('注册时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
