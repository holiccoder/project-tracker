<?php

namespace App\Filament\Resources\DevLogs\Pages;

use App\Filament\Resources\DevLogs\DevLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDevLog extends CreateRecord
{
    protected static string $resource = DevLogResource::class;
}
