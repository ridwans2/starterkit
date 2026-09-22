<?php

/*
|--------------------------------------------------------------------------
| Guarda dos rótulos de painel que viraram getter
|--------------------------------------------------------------------------
| A Fase 3 não consegue exists enquanto um rótulo morar numa propriedade
| estática: PHP exige expressão constante, então `protected static ?string
| $modelLabel = 'Convite'` não pode conter `__()`, e o upstream devolve essa
| linha inteira a cada `kit:update` (`git checkout <tag> -- <path>`, sem
| three-way merge). O trabalho estrutural é trocar propriedade por getter sem
| mover UMA letra do português — é exatamente isso que estes casos travam.
|
| Reflexão, não regex: `tests/Pest.php` e `.ai/rules/testes.md` registram três
| incidentes em que varredura de texto contou comentário como código. Aqui o
| oráculo lê a declaração real da classe, então docblock em português (que neste
| projeto é a norma) não interfere nem ajuda.
|
| `tests/Feature/` é pasta do projeto: o kit não a encosta, então nem esta guarda
| nem o que ela afirma aparecem no diff do `kit:update`.
*/

use App\Filament\Admin\Pages\ConfiguracoesDoKit;
use App\Filament\Admin\Pages\HubDeAdministracao;
use App\Filament\Admin\Resources\AgentesIa\AgenteIaResource;
use App\Filament\Admin\Resources\Convites\ConviteResource as ConviteDoAdmin;
use App\Filament\Admin\Resources\Tenants\RelationManagers\UsersRelationManager;
use App\Filament\Admin\Resources\Users\UserResource as UsuarioDoAdmin;
use App\Filament\App\Pages\HubDoNegocio;
use App\Filament\App\Resources\Convites\ConviteResource as ConviteDoApp;
use App\Filament\App\Resources\Projetos\ProjetoResource;
use App\Filament\App\Resources\Users\UserResource as UsuarioDoApp;
use App\Filament\Infra\Pages\HubDeInfraestrutura;
use App\Filament\Infra\Resources\AiRuns\AiRunResource;
use App\Filament\Pages\Auth\EscolhaDePainel;
use App\Filament\Pages\BoasVindas;
use App\Models\Tenant;
use Filament\Pages\Page;

/**
 * Linha por classe: [classe, tipo, rótulo esperado por getter].
 *
 * `Pulse` fica fora de propósito: seu rótulo já é 'Pulse' nos dois idiomas. Os
 * dois widgets com `$subheading` também ficam, e por outro motivo — medido: nem
 * `Filament\Widgets\Widget` nem `leandrocfe/filament-apex-charts` declaram
 * `subheading` no v5, ou seja a propriedade nunca foi lida por ninguém. Trocá-la
 * por getter inventaria um subtítulo que hoje não existe na tela.
 *
 * @return array<int, array{0: class-string, 1: string, 2: array<string, string>}>
 */
function rotulosQueViraramGetter(): array
{
    return [
        [
            ConfiguracoesDoKit::class, 'page', [
                'getTitle'           => 'Configurações da aplicação',
                'getNavigationLabel' => 'Configurações da aplicação',
            ],
        ],
        [
            HubDeAdministracao::class, 'page', [
                'getTitle'           => 'Hub de administração',
                'getNavigationLabel' => 'Hub de administração',
            ],
        ],
        [
            HubDoNegocio::class, 'page', [
                'getTitle'           => 'Início',
                'getNavigationLabel' => 'Início',
            ],
        ],
        [
            HubDeInfraestrutura::class, 'page', [
                'getTitle'           => 'Central de infraestrutura',
                'getNavigationLabel' => 'Central de infraestrutura',
            ],
        ],
        [
            // Só `$title` existia aqui; o rótulo de navegação vinha do fallback do
            // vendor (`$navigationLabel ?? $title`). Retirar a propriedade sem
            // declarar o getter trocaria o texto pelo `class_basename`.
            EscolhaDePainel::class, 'page', [
                'getTitle'           => 'Em qual painel você quer entrar?',
                'getNavigationLabel' => 'Em qual painel você quer entrar?',
            ],
        ],
        [
            BoasVindas::class, 'page', [
                'getTitle'           => 'Bem-vindo ao Starter Kit Easy',
                'getNavigationLabel' => 'Bem-vindo ao Starter Kit Easy',
            ],
        ],
        [
            AgenteIaResource::class, 'resource', [
                'getModelLabel'       => 'Agente de IA',
                'getPluralModelLabel' => 'Agentes de IA',
            ],
        ],
        [
            ConviteDoAdmin::class, 'resource', [
                'getModelLabel'       => 'Convite',
                'getPluralModelLabel' => 'Convites',
            ],
        ],
        [
            ConviteDoApp::class, 'resource', [
                'getModelLabel'       => 'Convite',
                'getPluralModelLabel' => 'Convites',
            ],
        ],
        [
            ProjetoResource::class, 'resource', [
                'getModelLabel'       => 'Projeto',
                'getPluralModelLabel' => 'Projetos',
            ],
        ],
        [
            UsuarioDoAdmin::class, 'resource', [
                'getModelLabel'       => 'Usuário',
                'getPluralModelLabel' => 'Usuários',
            ],
        ],
        [
            UsuarioDoApp::class, 'resource', [
                'getModelLabel'       => 'Usuário',
                'getPluralModelLabel' => 'Usuários',
            ],
        ],
        [
            AiRunResource::class, 'resource', [
                'getNavigationLabel'  => 'Execuções de IA',
                'getModelLabel'       => 'Execução de IA',
                'getPluralModelLabel' => 'Execuções de IA',
            ],
        ],
        [
            // `RelationManager::getTitle(Model, string)` é estática e chamada com
            // argumentos pelo vendor (`static::getTitle(...)`), enquanto
            // `BasePage::getTitle()` é de instância. Os dois tipos coexistem no
            // kit; inverter qualquer um dos dois é `Error` em tempo de execução.
            UsersRelationManager::class, 'relation', [
                'getTitle'            => 'Usuários vinculados',
                'getModelLabel'       => 'Usuário',
                'getPluralModelLabel' => 'Usuários',
            ],
        ],
    ];
}

/**
 * Propriedades de rótulo declaradas pela própria classe (não herdadas).
 *
 * @return array<int, string>
 */
function propriedadesDeRotuloEstaticas(string $classe): array
{
    $achadas = [];

    foreach (['title', 'modelLabel', 'pluralModelLabel', 'navigationLabel', 'subheading'] as $nome) {
        if (! property_exists($classe, $nome)) {
            continue;
        }

        $propriedade = new ReflectionProperty($classe, $nome);

        if ($propriedade->isStatic() && $propriedade->getDeclaringClass()->getName() === $classe) {
            $achadas[] = $nome;
        }
    }

    return $achadas;
}

it('mantém cada rótulo de painel fora de propriedade estática', function (string $classe): void {
    $estaticas = propriedadesDeRotuloEstaticas($classe);

    $this->assertSame(
        [],
        $estaticas,
        "{$classe} devolveu o rótulo a propriedade estática ([".implode(', ', $estaticas).']) — '
        .'a Fase 3 precisa de getter para poder traduzir, e é assim que um `kit:update` revertido aparece.'
    );
})->with(array_column(rotulosQueViraramGetter(), 0));

it('lê o rótulo pelo getter declarado pela própria classe, com a static-ness do vendor', function (string $classe, string $tipo, array $rotulos): void {
    foreach ($rotulos as $metodo => $textoPortugues) {
        $reflexao = new ReflectionMethod($classe, $metodo);

        $this->assertSame($classe, $reflexao->getDeclaringClass()->getName(), "{$classe}::{$metodo} não é declarado pela própria classe");

        // `BasePage::getTitle()` é de instância no v5; os demais getters de rótulo
        // são estáticos. Um revertendo ao outro é fatal em runtime, não aqui.
        $esperadaEstatica = ! ($tipo === 'page' && $metodo === 'getTitle');
        $this->assertSame($esperadaEstatica, $reflexao->isStatic(), "{$classe}::{$metodo} mudou de static-ness");
    }
})->with(rotulosQueViraramGetter());

it('não move uma letra do português ao trocar propriedade por getter', function (string $classe, string $tipo, array $rotulos): void {
    $instancia = $tipo === 'page' ? new $classe : null;

    foreach ($rotulos as $metodo => $textoPortugues) {
        $valor = match ($tipo) {
            'page'     => $metodo === 'getTitle' ? $instancia->getTitle() : $classe::getNavigationLabel(),
            'relation' => $metodo === 'getTitle'
                ? $classe::getTitle(new Tenant, 'X')
                : (string) (new ReflectionMethod($classe, $metodo))->invoke(null),
            default => (string) $classe::{$metodo}(),
        };

        $this->assertSame($textoPortugues, $valor, "{$classe}::{$metodo}() não devolve o texto que a propriedade guardava");
    }
})->with(rotulosQueViraramGetter());

it('vê a propriedade estática quando ela existe (controle positivo do oráculo)', function (): void {
    /*
     * Sem este caso, as guardas acima passam para sempre sobre uma classe que não
     * existe mais: `property_exists()` falso e laço sobre dataset vazio são ambos
     * verdes por omissão. É o "46 casos verdes sobre qualquer coisa" de
     * `tests/Pest.php` — o oráculo precisa provar que caça.
     */
    $fixture = new class extends Page
    {
        protected static ?string $modelLabel = 'Organização';
    };

    $this->assertSame(['modelLabel'], propriedadesDeRotuloEstaticas($fixture::class));
});
