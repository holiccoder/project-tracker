<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = '付款记录';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('amount')
                    ->label('金额')
                    ->numeric()
                    ->required()
                    ->minValue(0.01)
                    ->prefix('¥'),
                DatePicker::make('date')
                    ->label('付款日期')
                    ->required()
                    ->default(now()),
                Textarea::make('remark')
                    ->label('备注（客户可见）')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('remark')
            ->columns([
                TextColumn::make('amount')
                    ->label('金额')
                    ->money('CNY')
                    ->sortable(),
                TextColumn::make('date')
                    ->label('日期')
                    ->date()
                    ->sortable(),
                TextColumn::make('remark')
                    ->label('备注')
                    ->limit(50),
                TextColumn::make('creator.name')
                    ->label('记录人'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['created_by'] = auth('admin')->id();
                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()->modalHeading('删除付款记录'),
            ]);
    }
}
