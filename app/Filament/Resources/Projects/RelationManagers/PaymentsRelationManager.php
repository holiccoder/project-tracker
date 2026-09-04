<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Support\FilamentInputFactory;
use App\Support\InputSchemaRegistry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('project_payments', 'amount'),
                FilamentInputFactory::make('project_payments', 'date'),
                FilamentInputFactory::make('project_payments', 'remark')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('remark')
            ->columns([
                TextColumn::make('amount')->label(InputSchemaRegistry::field('project_payments', 'amount')['label'])->money('CNY')->sortable(),
                TextColumn::make('date')->label(InputSchemaRegistry::field('project_payments', 'date')['label'])->date()->sortable(),
                TextColumn::make('remark')->label(InputSchemaRegistry::field('project_payments', 'remark')['label'])->limit(50),
                TextColumn::make('creator.name')->label('Recorded by'),
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
                DeleteAction::make(),
            ]);
    }
}
