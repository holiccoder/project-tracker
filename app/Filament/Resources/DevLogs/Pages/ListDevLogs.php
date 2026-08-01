<?php

namespace App\Filament\Resources\DevLogs\Pages;

use App\Filament\Resources\DevLogs\DevLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDevLogs extends ListRecords
{
    protected static string $resource = DevLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
