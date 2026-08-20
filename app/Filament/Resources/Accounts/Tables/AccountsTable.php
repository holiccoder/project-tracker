<?php

namespace App\Filament\Resources\Accounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('website_name')
                    ->label('网站名称')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label('所属项目')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('username')
                    ->label('用户名')
                    ->searchable(),
                TextColumn::make('login_url')
                    ->label('登录地址')
                    ->limit(40)
                    ->url(fn ($record): string => $record->login_url)
                    ->openUrlInNewTab(),
                TextColumn::make('note')
                    ->label('备注')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('所属项目')
                    ->relationship('project', 'name'),
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
