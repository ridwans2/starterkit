<?php

/*
|--------------------------------------------------------------------------
| Idioma e formatação do projeto
|--------------------------------------------------------------------------
| Este arquivo é DO PROJETO, não do kit. Ele existe fora de
| `KitUpdate::CAMINHOS_DO_KIT` de propósito: o `kit:update` compara duas tags
| do upstream entre si, então um arquivo que só existe aqui nunca aparece no
| diff e nunca é oferecido para aplicar. É o que permite trocar o idioma
| padrão sem que a próxima atualização do kit reverta a troca em silêncio.
|
| Ele responde três perguntas — e só essas três:
|
|   1. quais idiomas o usuário pode escolher? (`idiomas`)
|   2. em qual idioma a SUÍTE DE TESTE mede o kit? (`suite`)
|   3. que máscara de data/número cada idioma usa? (`formatos`)
|
| Quem responde "em qual idioma o app roda" é `APP_LOCALE` no `.env`, e o
| `config/app.php` já responde 'en' quando a chave está ausente. Não acrescente
| chave aqui que outra parte do sistema já governe (`.ai/rules/config.md`:
| "uma pergunta, uma dona").
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Idiomas oferecidos ao usuário
    |--------------------------------------------------------------------------
    | O kit nasce com `config/kit.php:idiomas => ['pt_BR']`, e é ESSA chave que o
    | seletor de idioma lê (`ConfiguraFilamentGlobal::configuraSeletorDeIdioma()`,
    | que já cuida de esconder o botão quando há um idioma só).
    |
    | Não editamos `config/kit.php` para isso: `tests/Kit/PacotesTierSTest.php`
    | afirma `config('kit.idiomas') === ['pt_BR']` e é arquivo do upstream —
    | trocar o valor deixa a suíte do kit vermelha, e trocar o teste é churn que o
    | próximo update reverte. Quem manda aqui é o
    | `App\Providers\LocalizacaoProvider`, que copia esta lista para `kit.idiomas`
    | no boot. Fora do contexto de teste, é a única dona do valor.
    */

    'idiomas' => ['en', 'pt_BR'],

    /*
    |--------------------------------------------------------------------------
    | Idioma da suíte de teste
    |--------------------------------------------------------------------------
    | A suíte mede a FUNDAÇÃO, não o projeto deste usuário — é o mesmo princípio
    | já declarado no cabeçalho do bloco `<php>` do `phpunit.xml` ("a suíte roda
    | contra a configuração DO KIT, não contra a do projeto").
    |
    | O valor é `pt_BR` porque ~90 arquivos de `tests/Kit` e `tests/Tenancy`
    | pertencem ao upstream e afirmam sobre o texto português renderizado. Com o
    | app em inglês por padrão e a suíte sem pino, todos eles falhariam sem que
    | nada estivesse quebrado — e o custo de manter 90 arquivos de teste do
    | upstream traduzidos é pago de novo a cada release do kit.
    |
    | O pino é aplicado por `App\Providers\LocalizacaoProvider`, não aqui
    | diretamente: em testes o `phpunit.xml` já é whitelisted do kit e uma linha
    | nele pode sumir no próximo update sem que ninguém veja.
    */

    'suite' => 'pt_BR',

    /*
    |--------------------------------------------------------------------------
    | Máscaras por idioma
    |--------------------------------------------------------------------------
    | As máscaras viviam repetidas em ~28 chamadas `dateTime('d/m/Y H:i')` pelo
    | app. Cada uma delas era um `pt_BR` escondido em código que deveria respeitar
    | o idioma corrente — e um `en` com dia antes do mês é um bug que ninguém vê
    | até um usuário americano marcar reunião no dia 3 de março.
    |
    | Medido no vendor (Filament 5.8.2): `Table::date()`/`dateTime()` sem formato
    | caem em `getDefaultDateDisplayFormat()`, definido em
    | `Filament\Support\Concerns\HasDefaultDataFormattingSettings` com default
    | `'M j, Y'` e setter público `defaultDateDisplayFormat()`. Existe, portanto,
    | um lugar só onde isto precisa ser aplicado — o `configuraTable()` global, que
    | já existe em `ConfiguraFilamentGlobal`. Os 28 chamadas hardcoded então SOMEM,
    | não são reescritas.
    */

    'formatos' => [
        'en' => [
            'data'      => 'Y-m-d',
            'data_hora' => 'Y-m-d H:i',
            'hora'      => 'H:i',
        ],
        'pt_BR' => [
            'data'      => 'd/m/Y',
            'data_hora' => 'd/m/Y H:i',
            'hora'      => 'H:i',
        ],
    ],

];
