<?php

namespace App\Filament\Resources\DevLogs;

use App\Filament\Resources\DevLogs\Pages\CreateDevLog;
use App\Filament\Resources\DevLogs\Pages\EditDevLog;
use App\Filament\Resources\DevLogs\Pages\ListDevLogs;
use App\Filament\Resources\DevLogs\Schemas\DevLogForm;
use App\Filament\Resources\DevLogs\Tables\DevLogsTable;
use App\Models\DevLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DevLogResource extends Resource
{
    protected static ?string $model = DevLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'content';

    public static function form(Schema $schema): Schema
    {
        return DevLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevLogs::route('/'),
            'create' => CreateDevLog::route('/create'),
            'edit' => EditDevLog::route('/{record}/edit'),
        ];
    }
}
