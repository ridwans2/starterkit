<?php

namespace App\Filament\Admin\Resources\Convites\Schemas;

use App\Support\AdministradorDaInstalacao;
use App\Support\Papeis;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Support\Config;

/**
 * Form do convite: e-mail, papel e (com tenancy) organização. Três campos, porque são as
 * três decisões que quem convida toma no lugar de quem vai aceitar.
 *
 * O prazo não é campo: sai de `kit.convites.validade_em_dias` e é gravado por
 * `Convite::enviar()`, no `afterCreate()` da página.
 */
class ConviteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Who, and with which access'))
                    ->description(__('The invitation is a single-use link, sent to the email below.'))
                    ->columns(2)
                    ->components([
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            /*
                             * SEM `->unique('users', 'email')`, e isto é a feature.
                             *
                             * Até a v0.11.0 o campo recusava endereço que já tinha conta, o
                             * que fazia do convite uma parede no caso mais comum de SaaS
                             * multi-tenant. Agora o endereço com conta vira OFERTA DE
                             * ACESSO: nenhuma conta nova é criada, a pessoa confirma
                             * autenticada e é vinculada à organização com este papel.
                             */
                            ->helperText(__('If the address already has an account, nobody is registered again: the person receives an offer to join this organization and chooses to accept or decline. With MAIL_MAILER=log the email is only written to storage/logs.'))
                            ->columnSpanFull(),

                        Select::make('role_id')
                            ->label(__('Role'))
                            // Recorte de UX do teto de escalada (F-01); a trava é o `->rule()`.
                            ->relationship('papel', 'name', fn (Builder $query): Builder => AdministradorDaInstalacao::recortarConcessao($query))
                            ->required()
                            ->preload()
                            ->searchable()
                            // Quem não é master_global não convida master_global — na
                            // ESCRITA, como o `role_id` do ConviteResource do /app (ADR-07).
                            ->rule(fn (): object => AdministradorDaInstalacao::regraDeConcessao())
                            // `->live()` porque o campo de organização depende do painel
                            // deste papel. O parâmetro TEM de se chamar `$record`: o
                            // Filament injeta closure de opção por NOME, não por tipo.
                            ->live()
                            ->getOptionLabelFromRecordUsing(function (Model $record): string {
                                $painel = $record->getAttribute('painel');

                                return Papeis::rotulo((string) $record->getAttribute('name')).' — '.(is_string($painel) ? "/{$painel}" : 'sem painel');
                            })
                            ->helperText(__('It is the role that grants panel access — whoever accepts starts with it.')),

                        Select::make('tenant_id')
                            ->label(config('kit.tenancy.label', 'Organização'))
                            ->relationship('tenant', 'nome')
                            ->preload()
                            ->searchable()
                            ->visible(fn (): bool => (bool) config('kit.tenancy.enabled'))
                            /*
                             * Sem isto o convite nasceria com papel do /app e sem
                             * organização, e o aceite atribuiria o papel no contexto
                             * global: alguém que entra no painel de negócio e não
                             * enxerga organização nenhuma.
                             */
                            ->required(fn (Get $get): bool => (bool) config('kit.tenancy.enabled')
                                && self::painelDoPapel($get('role_id')) === 'app')
                            ->helperText(__('The business panel role needs an organization: that is where the role will be assigned.')),
                    ]),
            ]);
    }

    /**
     * O painel declarado pelo papel escolhido.
     *
     * Lido por query e não por type hint concreto: a classe do papel sai de
     * `permission.models.role` em runtime.
     */
    private static function painelDoPapel(mixed $roleId): ?string
    {
        if (blank($roleId)) {
            return null;
        }

        $modelo = Config::roleModel();
        $painel = $modelo::query()->whereKey($roleId)->value('painel');

        return is_string($painel) ? $painel : null;
    }
}
