<?php

/*
|--------------------------------------------------------------------------
| Guarda dos identificadores do banco
|--------------------------------------------------------------------------
| Este é o alarme do rename de tabelas. As quatro tabelas em português foram
| renomeadas por uma migration nova, mas os `protected $table` que apontam para
| elas vivem em arquivos do upstream — e um `kit:update` que devolva
| `app/Models/Convite.php` na versão do kit não quebra nada visível: apaga a
| linha, a convenção sobre o nome da classe volta a sugerir `convites`, e o
| primeiro `SELECT` da tela morre em produção. É o pior tipo de regressão
| possível, porque o diff do update parece inofensivo.
|
| Os dois sentidos são de propósito. `hasTable(ingles)` prova que a migration
| sobreviveu; `hasTable(portugues) === false` e `getTable()` provam que o MODEL
| sobreviveu. Um deles sozinho deixa passar metade do defeito.
*/

use App\Models\AgenteIa;
use App\Models\Convite;
use App\Models\Projeto;
use App\Models\VinculoSocial;
use Illuminate\Support\Facades\Schema;

/** @var list<array{string, string}> */
$tabelas = [
    ['agentes_ia', 'ai_agents'],
    ['convites', 'invitations'],
    ['projetos', 'projects'],
    ['vinculos_sociais', 'social_links'],
];

it('tem as quatro tabelas com nome em inglês no esquema migrado', function (string $pt, string $en): void {
    expect(Schema::hasTable($en))->toBeTrue("a tabela `{$en}` sumiu — a migration de rename deixou de existir?")
        ->and(Schema::hasTable($pt))->toBeFalse("`{$pt}` voltou: o rename não rodou ou foi revertido");
})->with($tabelas);

it('aponta cada model para a tabela renomeada, e não para a convenção do nome da classe', function (string $pt, string $en): void {
    $model = match ($en) {
        'ai_agents'    => AgenteIa::class,
        'invitations'  => Convite::class,
        'projects'     => Projeto::class,
        'social_links' => VinculoSocial::class,
    };

    /*
     * `getTable()` é o que o Eloquent realmente usa no `FROM`. Se o upstream
     * devolveu o arquivo e a linha `$table` sumiu, este é o único lugar onde isso
     * aparece antes de um query estourar.
     */
    expect((new $model)->getTable())->toBe($en);
})->with($tabelas);

it('não deixa nenhuma tabela portuguesa no esquema migrado', function (): void {
    $nomes = array_column(Schema::getTables(), 'name');

    $portugues = array_values(array_intersect(
        ['agentes_ia', 'convites', 'projetos', 'vinculos_sociais'],
        $nomes,
    ));

    expect($portugues)->toBe([], 'tabelas do idioma de origem ainda existem no esquema');
});
