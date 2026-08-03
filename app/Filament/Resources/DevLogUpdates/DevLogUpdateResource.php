<?php

namespace App\Filament\Resources\DevLogUpdates;

use App\Filament\Resources\DevLogUpdates\Pages\CreateDevLogUpdate;
use App\Filament\Resources\DevLogUpdates\Pages\EditDevLogUpdate;
use App\Filament\Resources\DevLogUpdates\Pages\ListDevLogUpdates;
use App\Filament\Resources\DevLogUpdates\Schemas\DevLogUpdateForm;
use App\Filament\Resources\DevLogUpdates\Tables\DevLogUpdatesTable;
use App\Models\DevLogUpdate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DevLogUpdateResource extends Resource
{
    protected static ?string $model = DevLogUpdate::class;

    protected static ?string $navigationLabel = '日志更新';

    protected static ?string $pluralModelLabel = '日志更新';

    protected static ?string $modelLabel = '日志更新';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'update';

    public static function form(Schema $schema): Schema
    {
        return DevLogUpdateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DevLogUpdatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDevLogUpdates::route('/'),
            'create' => CreateDevLogUpdate::route('/create'),
            'edit' => EditDevLogUpdate::route('/{record}/edit'),
        ];
    }
}
