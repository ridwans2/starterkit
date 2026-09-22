<?php

namespace App\Filament\Concerns;

use App\Models\User;
use App\Support\Formatos;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

/**
 * A ficha somente-leitura de uma conta, idêntica nos dois painéis.
 *
 * Irmã de `CabecalhoDeUsuario`, `SituacaoDaConta` e `AprovacaoDeCadastro`, pelo mesmo argumento:
 * o que uma conta É não muda por painel. O que muda é QUEM abre a tela, e isso é decidido pelo
 * recorte de `getEloquentQuery()` e pela policy — não pelo que a ficha mostra.
 *
 * ## O que esta seção NÃO contém, de propósito
 *
 * **As organizações da pessoa.** Elas entram só na ficha do /admin
 * (`App\Filament\Admin\Resources\Users\Schemas\UserInfolist`). No /app, listar as organizações de
 * um usuário contaria a quem administra a Acme que aquela pessoa também é da Globex — o recorte de
 * `UserResource::getEloquentQuery()` (`app/Filament/App/Resources/Users/UserResource.php:197`)
 * garante que só se veja gente DA organização corrente, e não que se possa ver onde mais ela está.
 * Fronteira de tenancy vazada por campo de exibição é a variedade que nenhum teste de rota pega.
 *
 * **A senha, o token e o `remember_token`.** Não estão no `$hidden` por acaso
 * (`app/Models/User.php:$hidden:96-99`), e ficha de leitura não é o lugar de reabri-los.
 */
trait FichaDeUsuario
{
    /**
     * Identidade e estado da conta — o que vale igual nos dois painéis.
     *
     * A situação sai de `User::rotuloDaSituacao()`, a mesma decisão que a coluna da listagem e o
     * cabeçalho usam. Três consumidores, um `match`.
     */
    protected static function secaoDaConta(): Section
    {
        return Section::make(__('Account'))
            ->columns(2)
            ->schema([
                TextEntry::make('name')->label(__('Name')),
                TextEntry::make('email')->label(__('Email'))->copyable(),
                TextEntry::make('situacao')
                    ->label(__('Status'))
                    ->badge()
                    ->state(fn (User $record): string => $record->rotuloDaSituacao())
                    ->color(fn (User $record): string => $record->corDaSituacao()),
                TextEntry::make('origem')
                    ->label(__('Source'))
                    ->state(fn (User $record): string => $record->rotuloDaOrigem()),
                TextEntry::make('email_verified_at')
                    ->label('E-mail confirmado em')
                    ->dateTime(Formatos::dataHora())
                    ->placeholder(__('Not confirmed')),
                TextEntry::make('created_at')->label(__('Registered at'))->dateTime(Formatos::dataHora()),
                TextEntry::make('updated_at')->label(__('Updated at'))->dateTime(Formatos::dataHora()),
                /*
                 * NAO ha entrada `deleted_at` aqui, e a ausencia e deliberada — a primeira versao
                 * tinha uma, com `->visible(fn (User $record) => $record->trashed())`, e ela era
                 * CODIGO MORTO: conta excluida logicamente nao chega a esta tela.
                 *
                 * O route binding passa por `getEloquentQuery()`, e o `Resource::getEloquentQuery()`
                 * do Filament nao remove o `SoftDeletingScope` — o unico registro que satisfaria o
                 * `->visible()` e exatamente o que responde 404. Ver o comentario da `ViewAction`
                 * em `UserResource::table()`.
                 *
                 * Quando a conta excluida precisar de ficha, o caminho e alargar o binding, e ai
                 * a entrada volta junto com o caso que a cobre.
                 */
            ]);
    }
}
