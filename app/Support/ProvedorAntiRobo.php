<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Support\Contracts\HasLabel;

/**
 * Os provedores do desafio anti-robô das telas públicas — e o que sobrou do enum depois do pacote.
 *
 * Quem fala com o provedor agora é o `ddr/filament-captcha`: script, widget, `siteverify` e a
 * pontuação do reCAPTCHA v3 vivem nos drivers dele (`vendor/ddr/filament-captcha/src/Drivers/`).
 * O `value` de cada caso é o **nome do driver no pacote** (`CaptchaManager::createDriver()`,
 * `vendor/ddr/filament-captcha/src/CaptchaManager.php:33-44`) — e é também a string que vai em
 * `KIT_ANTI_ROBO_PROVEDOR` e em `kit.login.anti_robo.provedor`. Foi por isso que `recaptcha`
 * virou `recaptcha_v2`: o pacote precisa distinguir do `recaptcha_v3`, e a migration de settings
 * `2026_08_31_100000` converte o valor gravado (ADR-04 da wiki `adotar-ddr-filament-captcha`).
 *
 * O que o kit ainda precisa saber de cada um, e o pacote não tem: o rótulo em português e onde
 * criar o par de chaves. É só isso que fica aqui. Quem decide se a proteção está no ar continua
 * sendo `ConfiguracaoDoLogin::antiRobo()`, que devolve um caso daqui ou `null`.
 */
enum ProvedorAntiRobo: string implements HasLabel
{
    case RecaptchaV2 = 'recaptcha_v2';

    case RecaptchaV3 = 'recaptcha_v3';

    case Turnstile = 'turnstile';

    case Hcaptcha = 'hcaptcha';

    public function getLabel(): string
    {
        return match ($this) {
            self::RecaptchaV2 => __('Google reCAPTCHA v2 ("I\'m not a robot" checkbox)'),
            self::RecaptchaV3 => __('Google reCAPTCHA v3 (invisible, scored)'),
            self::Turnstile   => 'Cloudflare Turnstile',
            self::Hcaptcha    => 'hCaptcha',
        };
    }

    /** Só o v3 não mostra caixa: o token nasce sozinho e o servidor compara a pontuação com o limiar. */
    public function usaPontuacao(): bool
    {
        return $this === self::RecaptchaV3;
    }

    /** Onde criar o par de chaves — o roteiro do `helperText` da tela e do README. */
    public function ondeCriarAsChaves(): string
    {
        return match ($this) {
            self::RecaptchaV2 => __('google.com/recaptcha/admin → Create → type "Challenge (v2)", the "I am not a robot" box, with your domain. Copy the site key and the secret key.'),
            self::RecaptchaV3 => __('google.com/recaptcha/admin → Create → type "Score (v3)", with your domain. Copy the site key and the secret key, and adjust the threshold below (0.5 is the usual starting point).'),
            self::Turnstile   => __('dash.cloudflare.com → Turnstile → Add widget, "Managed" mode, with your domain. Copy the Site Key and the Secret Key.'),
            self::Hcaptcha    => __('dashboard.hcaptcha.com → Sites → New, with your domain. The Site Key lives on the site; the Secret Key is in Settings.'),
        };
    }
}
