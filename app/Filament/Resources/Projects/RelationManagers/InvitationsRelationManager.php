<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Support\FilamentInputFactory;
use App\Support\InputSchemaRegistry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class InvitationsRelationManager extends RelationManager
{
    protected static string $relationship = 'invitations';

    protected static ?string $title = 'Invitations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FilamentInputFactory::make('project_invitations', 'email'),
                FilamentInputFactory::make('project_invitations', 'expires_at'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                TextColumn::make('email')->label(InputSchemaRegistry::field('project_invitations', 'email')['label'])->searchable()->sortable(),
                TextColumn::make('invite_link')
                    ->label('Invite link')
                    ->getStateUsing(fn ($record): string => url("/projects/invite/{$record->token}"))
                    ->copyable(),
                TextColumn::make('expires_at')->label(InputSchemaRegistry::field('project_invitations', 'expires_at')['label'])->dateTime()->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn ($record): string => $record->isExpired() ? 'expired' : 'valid')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['token'] = Str::random(32);
                        return $data;
                    }),
            ])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
