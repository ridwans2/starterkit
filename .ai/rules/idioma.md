# Idioma — English-first, com overlay pt_BR/id

Este projeto é um **fork** do `gsferro/filament-starter-kit-easy`, cuja língua de origem é o
português, e `php artisan kit:update` substitui arquivos inteiros
(`git checkout <tag> -- <path>`), não linha por linha. Sem estas regras, um único `kit:update`
devolve as telas ao português e ninguém sabe por quê.

## 1. O literal inglês no código é a única fonte de verdade

```php
->label(__('Organization'))   // ✅ a chave É o texto em inglês
->label(__('Organização'))    // ❌ chave na língua de origem
->label('Organização')        // ❌ crua: nunca poderá ser traduzida
```

`lang/pt_BR.json` e `lang/id.json` traduzem **a partir do** inglês. `lang/en/**` não existe, de
propósito: se for criado, passa a competir com o literal do código e os dois acabam mudando.

## 2. Não traduza o que `tests/Kit` afirma byte a byte

Métodos como `Convite::situacao()` e `User::canAccessTenant()` **guardam e comparam** strings
em português, e a suíte upstream afirma sobre elas. Nesses casos a identidade fica em
português e a tela passa por um mapa: `Convite::ROTULOS_DA_SITUACAO` + `rotuloDaSituacao()`,
`User::chaveDaSituacao()` + `rotuloDaSituacao()`. A suíte é pinada em `pt_BR`
(`config/localization.php:suite`), então `__('Accepted')` volta `'Aceito'` e os testes do kit
seguem verdes **sem serem tocados**. Nunca edite um teste em `tests/Kit` para vê-lo verde: o
que está errado é o overlay, não o teste.

## 3. Migração em massa só pelo `i18n:extract`

`php artisan i18n:extract --candidatos` acha literais em posição de texto; `--report` mostra só
os que já estão no dicionário; sem opção, ele escreve. O comando conhece as posições de texto da
UI, conhece o arg-1 de `Section::make()`/`Tab::make()`, **pula** `#[Attribute]` (ali `__()` é erro
de compilação: argumento de atributo tem de ser expressão constante) e recusa escrever arquivo que
não compila. A ordem certa:

1. adicionar os pares `inglês → português` em `lang/pt_BR.json`;
2. `php artisan i18n:extract`;
3. `php artisan test --compact tests/Feature/NenhumRotuloPortuguesNaTelaTest.php`.

Não redigite o português: componha a partir do que já está no overlay ou de
`git show HEAD:<arquivo>`. Um acento perdido = asserção de `tests/Kit` vermelha, e a tentação
passa a ser "consertar o teste".

## 4. O que faz isto sobreviver ao `kit:update`

`KitUpdate::CAMINHOS_DO_KIT` entrega **diretórios** (`app/Filament`, `app/Notifications`,
`app/Support`, `resources/views/livewire`, `resources/views/errors`), então quase todo arquivo
migrado pode voltar ao formato antigo. A defesa tem dois níveis, e eles não são o mesmo:

- **Estrutural** — arquivo que não existe upstream nunca aparece no diff entre tags e não pode
  ser substituído: `lang/*.json`, `config/localization.php`,
  `app/Providers/LocalizacaoProvider.php`, `app/Support/Formatos.php`,
  `app/Console/Commands/I18nExtract.php` e as guardas em `tests/Feature/`.
- **Auto-cura** — para arquivo que existe upstream: depois do `kit:update`, rode
  `i18n:extract`. Ele reaplica o inglês a partir do dicionário, incluindo as variantes **sem
  acento** (`'Ja configurada — em branco mantem'`), porque o dicionário é dobrado.

O que o extrator não alcança — por exemplo um `protected static ?string $heading = '…'` que
volte a ser propriedade — é nomeado pela guarda de tela, não pela memória de quem escreveu.

## 5. Armadilhas medidas (não as repita)

- `__()` no registro de provider **congela no boot**: o locale em boot é o do config, não o da
  request. Rótulo por request = getter.
- Grupo de navegação é **identificador**, não copy: tem de bater exatamente com o que o provider
  registra (`'Trails'`, `'Observability'`).
- `translateLabel()` se aplica a `->label()`, field, column, filter e action — **não** a headings
  nem ao arg-1 de `Section::make()`: ali o `__()` é escrito à mão.
- Uma palavra em inglês pode conter uma em português: `'Errors'` contém `'Erro'`, `'Contains'`
  contém `'Conta'`, `'Aceitos'` contém `'Aceito'`. A guarda compara com borda de letra
  `(?<!\p{L})…(?!\p{L})`, e não se cria chave cujo valor é idêntico à chave
  (`"Favicon": "Favicon"`).

## 6. Dado não é texto

Título de dashboard dinâmico, filtro salvo, `permissions.name` gerado pelo Shield e status
gravado em `ai_runs` são **linhas do banco**, não literais. Traduzi-los exige migração de dados
(ou mapa de exibição), não edição de código. Se aparecerem numa tela em inglês, a guarda
registra e eles são tratados como Fase 7.

## 7. Data e número também são idioma

Máscara não se escreve no código: `App\Support\Formatos::data()/dataHora()/dataHoraComSegundos()/
hora()` lê `config/localization.php:formatos`, e o default global é colocado uma única vez em
`ConfiguraFilamentGlobal::configuraTable()`. O getter de locale do Laravel é `getLocale()`, não
`locale()`.
