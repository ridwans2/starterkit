<?php

/*
|--------------------------------------------------------------------------
| Guarda do gancho global de rótulo
|--------------------------------------------------------------------------
| Todo o plano de idioma depende de UMA decisão: o literal do código é o
| inglês, e o português/indonéso voltam pela camada de tradução. Isto só é
| verdade se `translateLabel()` estiver ligado em toda superfície do Filament
| sem que ninguém precise escrever `__('…')` em ~300 call sites.
|
| O gancho mora em `App\Providers\LocalizacaoProvider::terjemahkanLabelDasar()`,
| arquivo que não existe na árvore do kit — portanto um `git checkout` do
| upstream nunca o desfaz. Estes casos são o que prova que ele continua ligado
| e, principalmente, que ele ainda ABRANGE as subclasses: se o Filament mover a
| hierarquia de `Column`/`Action`/`BaseFilter`, a configuração na base para de
| valer e a tela volta ao literal cru sem nenhum outro teste acusar.
*/

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

afterEach(function (): void {
    app()->setLocale((string) config('localization.suite', 'pt_BR'));
});

/**
 * Chave que já vive em `lang/pt_BR.json`, escrita pelo próprio mantenedor do
 * kit. Usar uma chave real é de propósito: prova o gancho contra o overlay de
 * verdade, não contra um fixture que só existe aqui dentro.
 */
const CHAVE_DE_CONTROLE = 'Repository';

const VALOR_PT = 'Repositório';

it('traduz rótulo de coluna de tabela sem que o autor escreva __()', function (): void {
    app()->setLocale('pt_BR');

    expect(TextColumn::make('kode')->label(CHAVE_DE_CONTROLE)->getLabel())
        ->toBe(VALOR_PT);
});

it('traduz rótulo de ação, filtro e campo de formulário', function (): void {
    app()->setLocale('pt_BR');

    expect(Action::make('x')->label(CHAVE_DE_CONTROLE)->getLabel())->toBe(VALOR_PT)
        ->and(SelectFilter::make('x')->label(CHAVE_DE_CONTROLE)->getLabel())->toBe(VALOR_PT)
        ->and(TextInput::make('x')->label(CHAVE_DE_CONTROLE)->getLabel())->toBe(VALOR_PT);
});

it('NÃO cobre heading de seção — superfície que continua pedindo __() explícito', function (): void {
    /*
     * Medido, não presumido: `Section::make($x)` guarda o argumento em
     * `$heading`, e `translateLabel()` só mexe em `$label`. Um heading escrito
     * cru no código não passa pelo overlay, então a extração não pode tratá-lo
     * como superfície coberta. Vale para todo construtor do tipo: os rótulos que
     * o gancho alcança são os declarados por `->label()`/`$label`, e o resto
     * (heading, `Text::make()` corpo,_badge_, tooltip) é trabalho manual.
     */
    app()->setLocale('pt_BR');

    $secao = Section::make(CHAVE_DE_CONTROLE);

    expect($secao->getLabel())->toBeNull()
        ->and((string) $secao->getHeading())->toBe(CHAVE_DE_CONTROLE);
});

it('devolve o literal cru quando o idioma é o inglês que está no código', function (): void {
    app()->setLocale('en');

    expect(TextColumn::make('kode')->label(CHAVE_DE_CONTROLE)->getLabel())
        ->toBe(CHAVE_DE_CONTROLE);
});

it('devolve o literal cru enquanto o overlay ainda não cobre a string', function (): void {
    /*
     * Este é o caso que viabiliza a extração por área. Enquanto `app/Filament`
     * estiver metade inglês e metade português, uma string sem chave não pode
     * virar `Repository` e nem estourar: ela tem que aparecer exatamente como
     * foi escrita. Sem isso, ligar o gancho global quebraria telas cuja
     * tradução ainda não foi escrita.
     */
    app()->setLocale('pt_BR');

    expect(TextColumn::make('kode')->label('Rótulo que ninguém cadastrou')->getLabel())
        ->toBe('Rótulo que ninguém cadastrou');
});

it('ainda permite que um call site desligue a tradução explicitamente', function (): void {
    app()->setLocale('pt_BR');

    expect(TextColumn::make('kode')->label(CHAVE_DE_CONTROLE)->translateLabel(false)->getLabel())
        ->toBe(CHAVE_DE_CONTROLE);
});
