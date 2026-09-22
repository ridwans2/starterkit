<?php

declare(strict_types=1);

namespace App\Filament\Infra\Widgets;

use App\Filament\Concerns\ExigePermissaoDoWidget;
use Filament\Widgets\StatsOverviewWidget;
use Fomvasss\AiTasks\Models\AiRun;
use Gsferro\FilamentStatPlusEasy\Widgets\StatPlus;
use Illuminate\Support\Facades\Schema;

/**
 * Consumo e confiabilidade das execuções de IA (`ai_runs`).
 *
 * Custo e tokens moram aqui, e não no painel admin, porque são grandeza de
 * INFRAESTRUTURA: quem olha isso está decidindo sobre orçamento, rate limit e
 * escolha de driver — não sobre o conteúdo dos agentes.
 */
class IaStats extends StatsOverviewWidget
{
    use ExigePermissaoDoWidget;

    protected static ?int $sort = 70;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('AI runs');
    }

    protected static function fonteDeDadosDisponivel(): bool
    {
        return (bool) rescue(
            fn (): bool => Schema::hasTable(self::tabela()),
            false,
            report: false,
        );
    }

    /**
     * @return array<int, StatPlus>
     */
    protected function getStats(): array
    {
        $total = AiRun::query()->count();

        // `dead` entra junto de `error`: para quem paga a conta, um job que
        // esgotou as tentativas é uma execução que falhou do mesmo jeito.
        $comErro = AiRun::query()->whereIn('status', ['error', 'dead'])->count();

        $tokens = (int) AiRun::query()->sum('tokens_in') + (int) AiRun::query()->sum('tokens_out');
        $custo  = (float) AiRun::query()->sum('cost');

        return [
            StatPlus::make(__('Runs'), $total)
                ->icon('heroicon-o-cpu-chip')
                ->iconColor('primary')
                ->accentColor('primary')
                ->description(__('Total recorded in the ledger')),

            StatPlus::make(__('With error'), $comErro)
                ->icon('heroicon-o-exclamation-circle')
                ->iconColor($comErro > 0 ? 'danger' : 'gray')
                ->accentColor($comErro > 0 ? 'danger' : 'gray')
                ->description($this->taxaDeErro($comErro, $total)),

            StatPlus::make('Tokens consumidos', $tokens)
                ->icon('heroicon-o-hashtag')
                ->iconColor('info')
                ->accentColor('info')
                // Entrada + saída num número só: é assim que o provedor cobra,
                // e separar os dois aqui só ocuparia espaço sem mudar decisão.
                ->description(__('Input + output, all executions')),

            StatPlus::make(__('Total cost'), $custo)
                ->icon('heroicon-o-banknotes')
                ->iconColor('warning')
                ->accentColor('warning')
                // O ledger grava `cost` com 8 casas decimais: arredondar em 2
                // esconderia execuções baratas somando um total relevante.
                ->format(['style' => 'currency', 'currency' => 'USD', 'maximumFractionDigits' => 4])
                ->description(__('Sum of `cost` recorded by the driver')),
        ];
    }

    private function taxaDeErro(int $comErro, int $total): string
    {
        if ($total === 0) {
            return 'Nenhuma execução registrada ainda';
        }

        return round(($comErro / $total) * 100, 1).'% das execuções';
    }

    private static function tabela(): string
    {
        return (string) config('ai-tasks.table', 'ai_runs');
    }
}
