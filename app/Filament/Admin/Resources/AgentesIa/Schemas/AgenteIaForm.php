<?php

namespace App\Filament\Admin\Resources\AgentesIa\Schemas;

use App\Ai\Guardrails\GuardrailRegistry;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Form do paper do agente, em três seções nomeadas pela decisão que se toma em cada uma:
 * quem é o agente, como ele executa, e o que o contém.
 *
 * O system prompt é o campo mais editado do formulário — por isso ocupa a largura inteira
 * com 14 linhas, em vez de dividir espaço com os parâmetros numéricos.
 */
class AgenteIaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Identifier'))
                    ->description(__('How the agent is recognized by the system and by the people maintaining it.'))
                    ->columns(2)
                    ->components([
                        TextInput::make('nome')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->helperText(__('Stable key read by the agent class (e.g. assistente). Changing it breaks the link with the code.'))
                            ->required()
                            ->maxLength(120)
                            ->unique(),
                        Textarea::make('descricao')
                            ->label(__('Description'))
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('ativo')
                            ->label(__('Active'))
                            ->helperText(__('Turning it off is the agent\'s "deletion" — the consumer degrades with an honest message.'))
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('Model and execution'))
                    ->description(__('Runtime parameters. Empty = the default in config/ai.php (env AI_PROVIDER).'))
                    ->columns(3)
                    ->components([
                        Select::make('provider')
                            ->label('Provider')
                            // Opções vindas do próprio config: o painel nunca oferece um provider
                            // que a aplicação não sabe resolver.
                            // `int|string` é o que `array_keys()` entrega: o PHP converte
                            // chave numérica em int, então um provider chamado "1" chegaria
                            // como int e o callback tipado `string` estouraria TypeError sob
                            // strict_types. O `(string)` desfaz essa conversão.
                            ->options(fn (): array => collect(array_keys((array) config('ai.providers', [])))
                                ->mapWithKeys(fn (int|string $chave): array => [(string) $chave => (string) $chave])
                                ->all())
                            ->searchable()
                            ->placeholder(__('Application default')),
                        TextInput::make('modelo')
                            ->label(__('Model'))
                            ->helperText(__('Empty = the provider default.'))
                            ->maxLength(120),
                        TextInput::make('temperatura')
                            ->label(__('Temperature'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(2)
                            ->step(0.1),
                        TextInput::make('max_tokens')
                            ->label('Max tokens')
                            ->numeric()
                            ->integer()
                            ->minValue(1),
                        TextInput::make('versao')
                            ->label(__('Paper version'))
                            ->helperText(__('Increment on every change to the instructions — it is what shows up in the audit trail.'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->default(1)
                            ->required(),
                    ]),

                Section::make('Comportamento')
                    ->description(__('What the agent may do and what contains it. Without a guardrail the agent does not come up.'))
                    ->columns(2)
                    ->components([
                        Textarea::make('instrucoes')
                            ->label(__('Instructions (system prompt)'))
                            ->rows(14)
                            ->required()
                            ->columnSpanFull(),
                        TagsInput::make('tools')
                            ->label('Tools (allowlist)')
                            // Texto livre de propósito: as chaves são definidas no mapa de
                            // fábricas do agente (App\Ai\Agents\Assistente::tools()), que é
                            // código do projeto, não do kit.
                            ->helperText(__('Keys of the enabled tools, following the agent factory map.')),
                        Select::make('guardrails')
                            ->label('Guardrails')
                            ->multiple()
                            // Só o que o GuardrailRegistry sabe resolver: nome desconhecido
                            // derrubaria o agente na primeira execução.
                            ->options(fn (): array => collect(array_keys(GuardrailRegistry::MAPA))
                                ->mapWithKeys(fn (string $chave): array => [$chave => $chave])
                                ->all())
                            ->helperText(__('Required: an agent without a guardrail is blocked on start-up. The list order is the execution order.')),
                    ]),
            ]);
    }
}
