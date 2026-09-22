<?php

namespace App\Providers;

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
        // Em teste, o app mede o kit — não a escolha deste projeto. Ver o docblock
        // da classe: ~90 arquivos de `tests/Kit` e `tests/Tenancy` afirmam sobre o
        // texto português renderizado, e pertencem ao upstream.
        if ($this->app->runningUnitTests()) {
            $this->app->setLocale((string) config('localization.suite', 'pt_BR'));
        }
    }
}
