<?php

namespace App\Filament\Widgets;

use App\Enums\AssetCondition;
use App\Filament\Resources\AssetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Aset: sepuluh badan usaha dengan nilai aset aktif terbesar.
 */
class AssetsByBusinessEntityChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Aset Aktif per Badan Usaha';

    protected ?string $description = '10 badan usaha dengan nilai perolehan terbesar.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = AssetResource::getEloquentQuery()->toBase()
            ->leftJoin('business_entities', 'business_entities.id', '=', 'assets.business_entity_id')
            ->whereIn('assets.condition_status', AssetCondition::transferableValues())
            ->selectRaw("COALESCE(business_entities.name, 'Tanpa badan usaha') AS entity, COUNT(*) AS total, COALESCE(SUM(assets.item_price * assets.qty), 0) AS value")
            ->groupBy('entity')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah aset',
                    'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::VIOLET, 0.35),
                    'borderColor' => ChartPalette::VIOLET,
                    'borderWidth' => 1,
                    'xAxisID' => 'x',
                ],
                [
                    'label' => 'Nilai perolehan',
                    'data' => $rows->pluck('value')->map(fn ($value): float => round((float) $value))->all(),
                    'backgroundColor' => ChartPalette::VIOLET,
                    'xAxisID' => 'x1',
                ],
            ],
            'labels' => $rows->pluck('entity')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::horizontalCombo('int', 'rupiah');
    }
}
