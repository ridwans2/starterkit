<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;
use App\Providers\Filament\InfraPanelProvider;
use App\Providers\KitServiceProvider;
use App\Providers\LocalizacaoProvider;

return [
    AppServiceProvider::class,
    KitServiceProvider::class,
    AdminPanelProvider::class,
    AppPanelProvider::class,
    InfraPanelProvider::class,
    // Por último de propósito: ele sobrescreve `kit.idiomas` depois de tudo que o
    // kit registra, e o seletor de idioma só lê essa config no render.
    LocalizacaoProvider::class,
];
