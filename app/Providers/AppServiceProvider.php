<?php

namespace App\Providers;

use App\Support\GerenciadorAntiRobo;
use Ddr\FilamentCaptcha\CaptchaManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O captcha do `ddr/filament-captcha` lendo a config DO KIT, com falha fechada e log. O
        // pacote registra o singleton dele em `packageRegistered()`; este provider é registrado
        // depois (app providers vêm após os descobertos), então o nosso vence. Há caso de teste.
        $this->app->singleton(CaptchaManager::class, fn (): CaptchaManager => new GerenciadorAntiRobo);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
