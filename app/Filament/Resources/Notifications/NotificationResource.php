<?php

namespace App\Filament\Resources\Notifications;

use App\Filament\Resources\Notifications\Pages\ListNotifications;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Notifications\DatabaseNotification;

class NotificationResource extends Resource
{
    protected static ?string $model = DatabaseNotification::class;

    protected static ?string $navigationLabel = '系统通知';

    protected static ?string $pluralModelLabel = '系统通知';

    protected static ?string $modelLabel = '系统通知';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?int $navigationSort = 7;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->limit(8)
                    ->searchable(),
                TextColumn::make('notifiable_type')
                    ->label('接收者类型')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'App\Models\Admin' => '管理员',
                        'App\Models\User' => '客户',
                        default => $state,
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('notifiable.name')
                    ->label('接收人')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('data.message')
                    ->label('通知内容')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('read_at')
                    ->label('阅读时间')
                    ->dateTime()
                    ->placeholder('未读')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('发送时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
        ];
    }
}
