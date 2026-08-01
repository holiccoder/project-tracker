<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\DevLogs\DevLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DevLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'devLogs';

    protected static ?string $relatedResource = DevLogResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
