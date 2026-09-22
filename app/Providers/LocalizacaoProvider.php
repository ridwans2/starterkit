<?php

namespace App\Providers;

use Filament\Actions\Action;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\ServiceProvider;

/**
 * Dono do idioma deste projeto — um provider DO PROJETO, não do kit.
 *
 * Ele existe fora de `KitUpdate::CAMINHOS_DO_KIT` de propósito: o `kit:update`
 * compara duas tags do upstream entre si, então um arquivo que só existe aqui
 * nunca aparece no diff. É isto que faz a troca de idioma padrão sobreviver à
 * próxima atualização do kit, em vez de voltar a `pt_BR` em silêncio.
 *
 * ## Por que `config()->set()` e não editar `config/kit.php:idiomas`
 *
 * `tests/Kit/PacotesTierSTest.php` afirma `config('kit.idiomas') === ['pt_BR']`
 * e que o seletor de idioma está INVISÍVEL. Os dois são arquivos do upstream:
 * editar o valor deixa a suíte do kit vermelha, e editar o teste é um churn que
 * o update seguinte reverte. O gate abaixo devolve à suíte o kit como ele nasce
 * — que é exatamente a doutrina já escrita no cabeçalho do bloco `<php>` do
 * `phpunit.xml` ("a suíte roda contra a configuração DO KIT, não contra a do
 * projeto") — e deixa a lista real onde o projeto pode mandá-la.
 *
 * O leitor (`ConfiguraFilamentGlobal::configuraSeletorDeIdioma()`) busca
 * `config('kit.idiomas')` DENTRO da closure de configuração, avaliada no render,
 * então um `set()` no boot chega sempre antes. Não mover a leitura para fora da
 * closure: foi um defeito real, documentado ali.
 */
class LocalizacaoProvider extends ServiceProvider
{
    /**
     * Kelas dasar cujas subclasses passam a pedir tradução sozinhas.
     *
     * `ComponentManager::configure()` resolve um registro contra
     * `[...array_reverse(class_parents($component)), $componentClass]`, então uma
     * entrada por base vale para toda a árvore de subclasses — inclusive as que o
     * Filament e os plugins de terceiro publicam e que não podemos editar.
     *
     * É isto que faz o literal do código ser o inglês: o overlay moram em
     * `lang/pt_BR.json` / `lang/id.json`, não em ~300 call sites de `__()`.
     *
     * @var list<class-string>
     */
    private const BASES_COM_LABEL = [
        Component::class,
        Column::class,
        ColumnGroup::class,
        BaseFilter::class,
        Summarizer::class,
        Action::class,
        Constraint::class,
    ];

    public function register(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        $this->aplicar();
    }

    /**
     * Aplica a lista de idiomas do projeto onde o Filament lê: `kit.idiomas`.
     *
     * Método público porque é isto que o teste de guarda exercita. Testar por
     * dentro de `register()` exigiria falsificar `APP_ENV` no meio da suíte, e um
     * guarda que precisa fingir o ambiente para provar que funciona não guarda
     * nada — ele só passa.
     */
    public function aplicar(): void
    {
        /** @var list<string> $idiomas */
        $idiomas = array_values((array) config('localization.idiomas', ['en']));

        config()->set('kit.idiomas', $idiomas);
    }

    public function boot(): void
    {
        // Roda também em teste: a suíte do kit afirma sobre o texto português
        // RENDERIZADO, e é o overlay que devolve esse texto. Sem o gancho aqui, o
        // pino de locale abaixo não tem o que traduzir.
        $this->terjemahkanLabelDasar();

        // Em teste, o app mede o kit — não a escolha deste projeto. Ver o docblock
        // da classe: ~90 arquivos de `tests/Kit` e `tests/Tenancy` afirmam sobre o
        // texto português renderizado, e pertencem ao upstream.
        if ($this->app->runningUnitTests()) {
            $this->app->setLocale((string) config('localization.suite', 'pt_BR'));
        }
    }

    /**
     * Liga `translateLabel()` em toda superfície de rótulo do Filament.
     *
     * O `method_exists()` não é cerimônia: `Filament\Schemas\Components\Component`
     * usa o `HasLabel` em cada subclasse concreta, não na base (medido em 5.8.2 —
     * `Section` declara o próprio `translateLabel`, `Column` declara na base). Um
     * componente sem rótulo simplesmente passa, e um componente que o upstream
     * publicar amanhã já vem coberto pela configuração da base dele.
     */
    private function terjemahkanLabelDasar(): void
    {
        foreach (self::BASES_COM_LABEL as $classeBase) {
            $classeBase::configureUsing(static function (object $komponen): object {
                if (! method_exists($komponen, 'translateLabel')) {
                    return $komponen;
                }

                return $komponen->translateLabel();
            });
        }
    }
}
