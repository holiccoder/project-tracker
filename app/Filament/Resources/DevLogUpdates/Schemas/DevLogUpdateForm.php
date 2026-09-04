<?php

namespace App\Filament\Resources\DevLogUpdates\Schemas;

use App\Support\FilamentInputFactory;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class DevLogUpdateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('dev_log_updates', 'dev_log_id')
                    ->relationship('devLog', 'content')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => sprintf(
                        '%s - %s',
                        $record->project?->name ?? 'Unnamed project',
                        str($record->content)->limit(60),
                    ))
                    ->searchable()
                    ->preload(),
                FilamentInputFactory::make('dev_log_updates', 'update')->columnSpanFull(),
            ]);
    }
}
