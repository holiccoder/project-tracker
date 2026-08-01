<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Issues\IssueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class IssuesRelationManager extends RelationManager
{
    protected static string $relationship = 'issues';

    protected static ?string $relatedResource = IssueResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}
