<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Concerns\ExigePermissaoDoWidget;
use App\Models\AgenteIa;
use Filament\Widgets\StatsOverviewWidget;
use Gsferro\FilamentStatPlusEasy\Widgets\StatPlus;

/**
 * Tamanho e saúde do catálogo de agentes de IA.
 *
 * O catálogo não tem exclusão física (a coluna `ativo` é a "lixeira"), então
 * "inativos" é informação de gestão e não resíduo: são agentes que existem,
 * ocupam slug e podem voltar a rodar com um clique.
 */
class AgentesIaStats extends StatsOverviewWidget
{
    use ExigePermissaoDoWidget;

    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Agentes de IA';

    /**
     * @return array<int, StatPlus>
     */
    protected function getStats(): array
    {
        $total  = AgenteIa::query()->count();
        $ativos = AgenteIa::query()->where('ativo', true)->count();

        return [
            StatPlus::make('Agentes cadastrados', $total)
                ->icon('heroicon-o-cpu-chip')
                ->iconColor('primary')
                ->accentColor('primary')
                ->description(__('Full catalog, active and inactive')),

            StatPlus::make('Ativos', $ativos)
                ->icon('heroicon-o-bolt')
                ->iconColor('success')
                ->accentColor('success')
                ->description(__('Available for execution')),

            StatPlus::make('Inativos', $total - $ativos)
                ->icon('heroicon-o-pause-circle')
                ->iconColor('gray')
                ->accentColor('gray')
                ->description(__('Turned off by the active flag')),
        ];
    }
}
