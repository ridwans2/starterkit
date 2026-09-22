<?php

namespace App\Filament\Exports;

use App\Models\AgenteIa;
use App\Support\ImportExport\ExportadorDoKit;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Export de agentes de IA — configuração, sem dado pessoal.
 *
 * `tools` e `guardrails` ficam fora: são `array` no cast, e array em célula de CSV vira
 * `Array` ou JSON escapado — ruído que não reimporta.
 */
class AgenteIaExporter extends ExportadorDoKit
{
    protected static ?string $model = AgenteIa::class;

    /**
     * @return array<ExportColumn>
     */
    protected static function colunas(): array
    {
        return [
            ExportColumn::make('slug')
                ->label('Slug'),
            ExportColumn::make('nome')
                ->label(__('Name')),
            ExportColumn::make('descricao')
                ->label(__('Description')),
            ExportColumn::make('ativo')
                ->label(__('Active')),
            ExportColumn::make('provider')
                ->label('Provider'),
            ExportColumn::make('modelo')
                ->label(__('Model')),
            ExportColumn::make('temperatura')
                ->label(__('Temperature')),
            ExportColumn::make('max_tokens')
                ->label(__('Maximum tokens')),
            ExportColumn::make('versao')
                ->label(__('Version')),
            ExportColumn::make('created_at')
                ->label(__('Created at')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $corpo = 'Exportação de agentes de IA concluída: '
            .Number::format($export->successful_rows).' '
            .str('linha')->plural($export->successful_rows).' exportada'
            .($export->successful_rows === 1 ? '' : 's').'.';

        if ($falhas = $export->getFailedRowsCount()) {
            $corpo .= ' '.Number::format($falhas).' '
                .str('linha')->plural($falhas).' falhou.';
        }

        return $corpo;
    }
}
