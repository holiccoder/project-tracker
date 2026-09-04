<?php

namespace App\Filament\Resources\Accounts\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class AccountForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('accounts', 'project_id')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                FilamentInputFactory::make('accounts', 'website_name'),
                FilamentInputFactory::make('accounts', 'login_url')->columnSpanFull(),
                FilamentInputFactory::make('accounts', 'username'),
                FilamentInputFactory::make('accounts', 'password')
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                FilamentInputFactory::make('accounts', 'note')->columnSpanFull(),
            ]);
    }
}
