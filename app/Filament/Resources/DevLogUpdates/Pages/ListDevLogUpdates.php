<?php

namespace App\Filament\Resources\DevLogUpdates\Pages;

use App\Filament\Resources\DevLogUpdates\DevLogUpdateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDevLogUpdates extends ListRecords
{
    protected static string $resource = DevLogUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
