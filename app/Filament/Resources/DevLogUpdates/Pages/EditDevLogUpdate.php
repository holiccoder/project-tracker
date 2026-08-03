<?php

namespace App\Filament\Resources\DevLogUpdates\Pages;

use App\Filament\Resources\DevLogUpdates\DevLogUpdateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDevLogUpdate extends EditRecord
{
    protected static string $resource = DevLogUpdateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
