<?php

namespace App\Filament\Concerns;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * A coluna, o filtro e as duas ações do estado ativo/inativo de um usuário.
 *
 * Irmã de `AprovacaoDeCadastro`, pelo mesmo argumento: são pedaços de UI cuja regra é idêntica
 * nos dois `UserResource` por definição — "Inativo" tem de significar a mesma coisa no /admin e
 * no /app. A coluna de situação morava naquela trait e mostrava só Pendente/Ativo; com o estado
 * novo ela ganhou o terceiro valor e veio para cá, onde o estado vive.
 *
 * As **ações** só o /admin usa (ADR-04 da wiki `status-e-exclusao-logica-de-usuario`): desativar
 * tira a pessoa de todas as organizações, é ato global como excluir, e o /app não o oferece pela
 * mesma régua da exclusão.
 *
 * A trait não é a barreira. Quem nega a entrada é `User::canAccessPanel()`; quem faz a transição
 * e recusa a própria conta e o último `master_global` é `User::desativar()`. Aqui só vive a
 * apresentação — e o `->authorize()`, que não é decoração: Action do Filament nasce liberada para
 * todo mundo (`vendor/filament/actions/src/Concerns/CanBeAuthorized.php:15-22`).
 */
trait SituacaoDaConta
{
    /**
     * Pendente, Inativo ou Ativo. A DECISÃO mora em `User::rotuloDaSituacao()`, não aqui: desde a
     * v0.36.0 os cabeçalhos de `UserHeader` dos dois painéis mostram a mesma coisa, e um `match`
     * copiado em três arquivos se desalinha na primeira mudança.
     */
    protected static function colunaDeSituacao(): TextColumn
    {
        return TextColumn::make('situacao')
            ->label(__('Status'))
            ->badge()
            ->state(fn (User $record): string => $record->rotuloDaSituacao())
            ->color(fn (User $record): string => $record->corDaSituacao());
    }

    protected static function filtroDeInativos(): Filter
    {
        return Filter::make('inativos')
            ->label(__('Only inactive'))
            ->query(fn (Builder $query): Builder => $query->where('ativo', false));
    }

    /**
     * Desativar — visível só para quem está ativo e pode ser desativado; autorizada por permissão
     * própria (`Desativar:User`, gerada por `filament-shield.resources.manage`).
     *
     * O `->visible()` espelha `User::motivoParaNaoDesativar()`, que é a regra de verdade e vale
     * para qualquer chamador. ponytail: sem `try/catch` da exceção do model — a ação já está oculta
     * quando a guarda vale; ela só lançaria numa corrida entre duas abas, e a mensagem é legível.
     */
    protected static function acaoDeDesativar(): Action
    {
        return Action::make('desativar')
            ->label(__('Deactivate'))
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->authorize('desativar')
            ->visible(fn (User $record): bool => $record->ativo
                && ! $record->trashed()
                && $record->motivoParaNaoDesativar() === null)
            ->requiresConfirmation()
            ->modalHeading(__('Deactivate this user?'))
            ->modalDescription(__('The person stops signing in to any panel until reactivated. Nothing is deleted.'))
            ->successNotificationTitle(__('User deactivated'))
            ->action(function (User $record): void {
                $record->desativar();
            });
    }

    protected static function acaoDeReativar(): Action
    {
        return Action::make('reativar')
            ->label(__('Reactivate'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->authorize('reativar')
            ->visible(fn (User $record): bool => ! $record->ativo && ! $record->trashed())
            ->requiresConfirmation()
            ->modalHeading(__('Reactivate this user?'))
            ->modalDescription(__('The person can sign in again to the panels of their role.'))
            ->successNotificationTitle(__('User reactivated'))
            ->action(function (User $record): void {
                $record->reativar();
            });
    }
}
