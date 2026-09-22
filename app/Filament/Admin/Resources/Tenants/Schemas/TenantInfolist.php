<?php

namespace App\Filament\Admin\Resources\Tenants\Schemas;

use App\Models\Tenant;
use App\Support\Formatos;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;

/**
 * A ficha somente-leitura de uma organização.
 *
 * ## Por que ela existe
 *
 * Sem `infolist()`, o `ViewRecord` cai no formulário DESABILITADO
 * (`vendor/filament/filament/src/Resources/Pages/ViewRecord.php:defaultInfolist:182`, que só é
 * consultado quando há componentes — sem eles a página monta o form). O Filament Blueprint chama
 * isso de *"looks poor"*, e aqui ficaria pior que o normal: a `ViewTenant` acabou de ganhar um
 * cabeçalho rico, e o contraste seria topo elaborado sobre campos cinzentos inertes.
 *
 * A `ViewTenant` é a tela que a receita de `wikis/receitas.md` usa como exemplo executável do
 * padrão "resumo do registro no topo + abas para os relacionamentos". Exemplo com o corpo meio
 * reformado ensina o meio errado.
 *
 * ## `columns(1)` no pai
 *
 * `defaultInfolist()` aplicaria `columns(2)`, e como cada `Section` daqui divide o interior em 2,
 * a entrada acabaria com 25% da largura. É o item "Nested Columns Too Narrow" do `checklist.md`
 * do Blueprint.
 */
class TenantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Identifier'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nome')->label(__('Name')),
                        TextEntry::make('slug')->label('Identificador')->copyable(),
                        /*
                         * O atalho para o painel da organização — RQ-01/RQ-03 da wiki
                         * `link-painel-do-tenant`. Aqui, ao lado do `slug` que é `copyable()`:
                         * o slug responde "qual é o identificador", este responde "onde ele
                         * leva". Nova aba (ADR-04).
                         */
                        TextEntry::make('url_do_painel')
                            ->label(__('Organization panel'))
                            ->state(fn (Tenant $record): ?string => $record->urlDoPainel())
                            ->url(fn (Tenant $record): ?string => $record->urlDoPainel())
                            ->openUrlInNewTab()
                            /*
                             * O ícone sinaliza a nova aba — QA-13 do quality gate.
                             *
                             * As outras duas superfícies já avisavam, cada uma do seu jeito: o
                             * formulário por `helperText` (tem espaço para prosa), a listagem por
                             * este mesmo ícone (não tem). Só a ficha não dizia nada, e abrir aba
                             * sem aviso é o tipo de surpresa que o usuário atribui a defeito.
                             *
                             * Ícone e não `helperText`: aqui a entrada fica ao lado do `slug`
                             * numa grade de fichas curtas, e uma linha de prosa por entrada
                             * desequilibraria a coluna. O mesmo ícone da listagem mantém o
                             * vocabulário visual único entre as duas telas de leitura.
                             */
                            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                            ->iconPosition(IconPosition::After),
                        TextEntry::make('ativo')
                            ->label(__('Status'))
                            ->badge()
                            ->state(fn (Tenant $record): string => $record->ativo ? 'Ativa' : 'Inativa')
                            ->color(fn (Tenant $record): string => $record->ativo ? 'success' : 'danger'),
                        TextEntry::make('registro_habilitado')
                            ->label(__('Open signup'))
                            ->badge()
                            ->state(fn (Tenant $record): string => $record->registro_habilitado ? 'Sim' : 'Não')
                            ->color(fn (Tenant $record): string => $record->registro_habilitado ? 'info' : 'gray'),
                        TextEntry::make('created_at')->label(__('Created at'))->dateTime(Formatos::dataHora()),
                        TextEntry::make('updated_at')->label(__('Updated at'))->dateTime(Formatos::dataHora()),
                    ]),
                Section::make(__('Visual identity'))
                    ->columns(2)
                    ->schema([
                        /*
                         * `urlDaLogo()` e não a coluna crua: ele confere
                         * `Storage::disk('public')->exists()` antes (`app/Models/Tenant.php:urlDaLogo:186`),
                         * então path órfão degrada para o placeholder em vez de renderizar imagem
                         * quebrada — que é o oposto do que a tela promete.
                         */
                        ImageEntry::make('logo')
                            ->label('Logo')
                            ->state(fn (Tenant $record): ?string => $record->urlDaLogo())
                            ->placeholder(__('No logo')),
                        TextEntry::make('cor_primaria_nome')
                            ->label(__('Palette color'))
                            ->placeholder(__('Application color (default)')),
                        TextEntry::make('cor_primaria')
                            ->label(__('Free color'))
                            ->placeholder('—')
                            ->helperText(__('When filled in, it beats the palette name.')),
                    ]),
            ]);
    }
}
