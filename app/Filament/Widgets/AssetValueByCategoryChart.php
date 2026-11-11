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
 * Dasbor > Aset: sepuluh kategori dengan nilai perolehan aset aktif terbesar,
 * beserta jumlah asetnya.
 */
class AssetValueByCategoryChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Nilai Aset Aktif per Kategori';

    protected ?string $description = '10 kategori dengan nilai perolehan terbesar (aset Tersedia dan Digunakan).';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 8;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = AssetResource::getEloquentQuery()->toBase()
            ->leftJoin('categories', 'categories.id', '=', 'assets.category_id')
            ->whereIn('assets.condition_status', AssetCondition::transferableValues())
            ->selectRaw("COALESCE(categories.name, 'Tanpa kategori') AS category, COUNT(*) AS total, COALESCE(SUM(assets.item_price * assets.qty), 0) AS value")
            ->groupBy('category')
            ->orderByDesc('value')
            ->limit(10)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah aset',
                    'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::PRIMARY, 0.35),
                    'borderColor' => ChartPalette::PRIMARY,
                    'borderWidth' => 1,
                    'xAxisID' => 'x',
                ],
                [
                    'label' => 'Nilai perolehan',
                    'data' => $rows->pluck('value')->map(fn ($value): float => round((float) $value))->all(),
                    'backgroundColor' => ChartPalette::SUCCESS,
                    'xAxisID' => 'x1',
                ],
            ],
            'labels' => $rows->pluck('category')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::horizontalCombo('int', 'rupiah');
    }
}
