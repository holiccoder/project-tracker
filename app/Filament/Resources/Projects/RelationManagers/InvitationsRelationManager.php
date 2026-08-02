<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Str;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = '项目邀请';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('客户邮箱')
                    ->email()
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('expires_at')
                    ->label('过期时间')
                    ->required()
                    ->default(now()->addDays(7)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')
                    ->label('邮箱')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invite_link')
                    ->label('邀请链接')
                    ->getStateUsing(fn ($record) => url("/projects/invite/{$record->token}"))
                    ->copyable()
                    ->copyMessage('邀请链接已复制')
                    ->description('点击链接单元格可直接复制'),
                TextColumn::make('expires_at')
                    ->label('过期时间')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->getStateUsing(fn ($record) => $record->isExpired() ? '已过期' : '有效')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        '已过期' => 'danger',
                        '有效' => 'success',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['token'] = Str::random(32);
                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
