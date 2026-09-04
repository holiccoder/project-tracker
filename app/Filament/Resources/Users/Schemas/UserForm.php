<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('users', 'name'),
                FilamentInputFactory::make('users', 'email'),
                FilamentInputFactory::make('users', 'wechat'),
                FilamentInputFactory::make('users', 'phone'),
                FilamentInputFactory::make('users', 'remark'),
                FilamentInputFactory::make('users', 'password')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
            ]);
    }
}
