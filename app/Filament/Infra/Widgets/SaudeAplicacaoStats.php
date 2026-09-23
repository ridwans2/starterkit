<?php

declare(strict_types=1);

namespace App\Filament\Infra\Widgets;

use App\Filament\Concerns\ExigePermissaoDoWidget;
use App\Support\Formatos;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Gsferro\FilamentStatPlusEasy\Widgets\StatPlus;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\ResultStores\ResultStore;

/**
 * Semáforo dos health checks: quantos passaram, quantos avisaram e quantos
 * quebraram na ÚLTIMA rodada.
 *
 * "Última rodada" e não histórico: health check é estado presente — saber que
 * o Redis caiu ontem não ajuda quem está olhando o painel agora. O
 * `latestResults()` do spatie devolve exatamente o último lote gravado.
 */
class SaudeAplicacaoStats extends StatsOverviewWidget
{
    use ExigePermissaoDoWidget;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return __('Application health');
    }

    /**
     * Sem a tabela de histórico não há o que mostrar — e o `latestResults()`
     * do store Eloquent consulta direto o model, então chamá-lo antes da
     * migration rodar estouraria a página.
     */
    protected static function fonteDeDadosDisponivel(): bool
    {
        return (bool) rescue(
            fn (): bool => Schema::hasTable((new HealthCheckResultHistoryItem)->getTable()),
            false,
            report: false,
        );
    }

    protected function getDescription(): ?string
    {
        $resultados = app(ResultStore::class)->latestResults();

        if ($resultados === null) {
            return __('No check executed yet — run `php artisan health:check`.');
        }

        return (string) __('Last check :at', ['at' => $resultados->finishedAt->format(Formatos::dataHora())]);
    }

    /**
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $contagem = $this->contarPorStatus();
        $falhando = $contagem['failed'] + $contagem['crashed'];

        return [
            StatPlus::make('Checks OK', $contagem['ok'])
                ->icon('heroicon-o-check-badge')
                ->iconColor('success')
                ->accentColor('success')
                ->description(__('Checks within expectations')),

            StatPlus::make(__('With warning'), $contagem['warning'])
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor($contagem['warning'] > 0 ? 'warning' : 'gray')
                ->accentColor($contagem['warning'] > 0 ? 'warning' : 'gray')
                ->description(__('Still working, but at the limit')),

            StatPlus::make(__('Failing'), $falhando)
                ->icon('heroicon-o-x-circle')
                ->iconColor($falhando > 0 ? 'danger' : 'gray')
                ->accentColor($falhando > 0 ? 'danger' : 'gray')
                // `crashed` (a checagem em si explodiu) entra junto de `failed`:
                // para quem está de plantão, os dois significam "vá olhar".
                ->description(__('Includes checks that crashed while running')),

            // Stat NATIVO, não StatPlus: o valor é texto ("Tudo certo"). O
            // odômetro só anima número e transformaria qualquer texto em 0.
            Stat::make(__('Overall status'), $this->resumo($contagem, $falhando))
                ->description($contagem['skipped'] > 0 ? (string) __(':count check(s) skipped', ['count' => $contagem['skipped']]) : __('No check skipped'))
                ->color($this->corDoResumo($contagem, $falhando)),
        ];
    }

    /**
     * @return array{ok: int, warning: int, failed: int, crashed: int, skipped: int}
     */
    private function contarPorStatus(): array
    {
        $contagem = ['ok' => 0, 'warning' => 0, 'failed' => 0, 'crashed' => 0, 'skipped' => 0];

        $resultados = app(ResultStore::class)->latestResults();

        if ($resultados === null) {
            return $contagem;
        }

        foreach ($resultados->storedCheckResults as $resultado) {
            if (array_key_exists($resultado->status, $contagem)) {
                $contagem[$resultado->status]++;
            }
        }

        return $contagem;
    }

    /**
     * @param  array{ok: int, warning: int, failed: int, crashed: int, skipped: int}  $contagem
     */
    private function resumo(array $contagem, int $falhando): string
    {
        return match (true) {
            array_sum($contagem) === 0 => __('No data'),
            $falhando > 0              => __('Immediate attention'),
            $contagem['warning'] > 0   => __('Under observation'),
            default                    => __('All good'),
        };
    }

    /**
     * @param  array{ok: int, warning: int, failed: int, crashed: int, skipped: int}  $contagem
     */
    private function corDoResumo(array $contagem, int $falhando): string
    {
        return match (true) {
            array_sum($contagem) === 0 => 'gray',
            $falhando > 0              => 'danger',
            $contagem['warning'] > 0   => 'warning',
            default                    => 'success',
        };
    }
}
