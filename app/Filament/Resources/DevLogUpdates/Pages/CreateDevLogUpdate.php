<?php

namespace App\Filament\Resources\DevLogUpdates\Pages;

use App\Filament\Resources\DevLogUpdates\DevLogUpdateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevLogUpdate extends CreateRecord
{
    protected static string $resource = DevLogUpdateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
