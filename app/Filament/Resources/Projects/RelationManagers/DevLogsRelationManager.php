<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\DevLogs\DevLogResource;
use App\Filament\Resources\DevLogs\Schemas\DevLogForm;
use Filament\Actions\CreateAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class DevLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'devLogs';

    protected static ?string $relatedResource = DevLogResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->label('批量添加开发日志')
                    ->modalHeading('批量添加开发日志')
                    ->createAnother(false)
                    ->schema([
                        DevLogForm::batchRepeater(),
                    ])
                    ->action(function (array $data): void {
                        foreach ($data['logs'] ?? [] as $log) {
                            $this->getRelationship()->create($log);
                        }
                    }),
            ]);
    }
}
