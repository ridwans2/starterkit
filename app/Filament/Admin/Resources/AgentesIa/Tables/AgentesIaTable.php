<?php

namespace App\Filament\Admin\Resources\AgentesIa\Tables;

use App\Support\Formatos;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Grid do catálogo de agentes. Sem DeleteAction — a "exclusão" é lógica, pela flag `ativo`:
 * apagar o paper de um agente que existe em código deixaria a aplicação com um agente que
 * não sobe.
 */
class AgentesIaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('nome')
            ->columns([
                TextColumn::make('nome')->label(__('Name'))->searchable(['nome', 'slug'])->sortable(),
                TextColumn::make('slug')->label('Slug')->badge()->color('gray')->searchable(),
                IconColumn::make('ativo')->label(__('Active'))->boolean(),
                TextColumn::make('provider')->label('Provider')->badge()->placeholder('default')->searchable(),
                TextColumn::make('modelo')->label(__('Model'))->placeholder('default')->searchable(),
                TextColumn::make('versao')->label(__('Version'))->badge()->color('gray')->sortable(),
                TextColumn::make('updated_at')->label(__('Updated at'))->dateTime(Formatos::dataHora())
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('ativo')->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->emptyStateHeading(__('No agents registered'))
            ->emptyStateDescription('Rode os seeders do kit (AssistenteSeeder, GuardaPromptSeeder) ou cadastre um agente.');
    }
}
