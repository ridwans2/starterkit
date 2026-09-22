<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Rename os quatro nomes de tabela ainda em português.
 *
 * `agentes_ia`, `convites`, `projetos` e `vinculos_sociais` são as quatro tabelas que
 * o kit herda do seu idioma de origem; todo o resto do esquema já nasce em inglês
 * (`users`, `tenants`, `roles`, `media`, `audits`, `ai_runs`…). `tenant_user` fica
 * como está de propósito: é pivot que Filament e o spatie inferem por nome, e
 * trocá-lo compra um ganho cosmético com risco real.
 *
 * ## Por que migration, e por que 2099
 *
 * As quatro tabelas já foram publicadas em tag. Editar a migration de origem é o
 * único caminho que quebra quem já está rodando — o `migrations` registra por NOME
 * de arquivo, então a criação antiga nunca roda de novo e o banco continua português
 * para sempre. Migration nova é o único caminho que alcança instalação existente.
 *
 * O prefixo `2099` é decisão, não descuido, e o motivo é o update do kit: o upstream
 * continua entregando migrations datadas (`2026_10_…_add_x_to_convites_table`). Se
 * este arquivo nascesse com a data de hoje, ele ordenaria ANTES dessas migrations e
 * elas estourariam contra uma tabela que não existe mais. Depois de 2099, toda
 * migration do kit roda primeiro e o rename acontece por último — sempre. Ordenar por
 * prefixo já é estilo da casa (`0001_01_01_0000{10,20,21,22}_*`).
 *
 * ## Os dois caminhos têm de dar no mesmo lugar
 *
 * - **Banco existente**: a criação antiga já foi registrada, só este rename roda, e
 *   `Schema::rename` leva os dados junto.
 * - **`migrate:fresh`**: as migrations históricas criam os nomes em português, este
 *   arquivo renomeia, e o resultado final é idêntico. É o que `php artisan
 *   kit:tenancy` exercita, nos dois modos.
 *
 * A guarda `hasTable` existe para um terceiro caminho, que é o deste fork: se um dia
 * as migrations históricas forem reescritas com os nomes em inglês (é o que o
 * extrator faz depois de um merge do upstream), `convites` nunca chega a existir e o
 * rename tem de ser no-op — não erro.
 *
 * Nomes de índice e constraint (`convites_email_unique`, `convites_role_id_foreign`)
 * continuam portugueses: renomeá-los em MySQL exige drop-and-recreate por índice, e
 * nada no app os lê. Quem audita o esquema por nome de constraint precisa saber disso.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const RENOMES = [
        'agentes_ia'       => 'ai_agents',
        'convites'         => 'invitations',
        'projetos'         => 'projects',
        'vinculos_sociais' => 'social_links',
    ];

    public function up(): void
    {
        $this->renomear(self::RENOMES);
    }

    public function down(): void
    {
        $this->renomear(array_flip(self::RENOMES));
    }

    /**
     * @param  array<string, string>  $dePara
     */
    private function renomear(array $dePara): void
    {
        foreach ($dePara as $de => $para) {
            if (! Schema::hasTable($de)) {
                continue;
            }

            Schema::rename($de, $para);
        }
    }
};
