<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Máscara de data/hora do idioma que está na tela.
 *
 * Existe porque o kit crava `d/m/Y H:i` em 25 pontos de código — o padrão do Brasil —
 * e um painel em inglês mostrando `22/09/2026 18:43` está dizendo outra coisa do que o
 * resto da frase ao lado. Não é capricho: a máscara é conteúdo de idioma tanto quanto
 * o rótulo da coluna.
 *
 * A dona do valor é `config/localization.php` (uma pergunta, uma dona). Ler daqui e não
 * do `Carbon` global é o que mantém o pt_BR idêntico ao que era: `formatos.pt_BR` carrega
 * exatamente as máscaras que estavam escritas à mão, então o que os testes do kit afirmam
 * sobre a tela em português não muda quando o locale é português.
 *
 * Chamado em tempo de request (dentro de `form()`/`table()`/`getOptions()`), nunca no boot
 * — é a armadilha do `__()` congelado em provider que já derrubou `TelaDePapeisTest`.
 */
final class Formatos
{
    public static function data(): string
    {
        return self::peca('data');
    }

    public static function dataHora(): string
    {
        return self::peca('data_hora');
    }

    public static function dataHoraComSegundos(): string
    {
        return self::peca('data_hora_segundos');
    }

    public static function hora(): string
    {
        return self::peca('hora');
    }

    private static function peca(string $nome): string
    {
        return (string) config('localization.formatos.'.app()->getLocale().'.'.$nome);
    }
}
