<?php

namespace App\Filament\Resources\DevLogs\Pages;

use App\Filament\Resources\DevLogs\DevLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevLog extends EditRecord
{
    protected static string $resource = DevLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
