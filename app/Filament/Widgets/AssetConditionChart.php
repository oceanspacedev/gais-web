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
 * Dasbor > Aset: komposisi kondisi aset (jumlah atau nilai perolehan).
 */
class AssetConditionChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Komposisi Kondisi Aset';

    protected ?string $description = 'Porsi tiap kondisi dari seluruh aset yang bisa Anda lihat.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    public ?string $filter = 'count';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getFilters(): ?array
    {
        return [
            'count' => 'Jumlah aset',
            'value' => 'Nilai perolehan',
        ];
    }

    protected function getData(): array
    {
        $rows = AssetResource::getEloquentQuery()->toBase()
            ->selectRaw('condition_status, COUNT(*) AS total, COALESCE(SUM(item_price * qty), 0) AS value')
            ->groupBy('condition_status')
            ->get()
            ->keyBy('condition_status');

        $cases = AssetCondition::cases();

        return [
            'datasets' => [[
                'label' => $this->filter === 'value' ? 'Nilai perolehan' : 'Jumlah aset',
                'data' => array_map(fn (AssetCondition $condition): int|float => $this->filter === 'value'
                    ? round((float) ($rows[$condition->value]->value ?? 0))
                    : (int) ($rows[$condition->value]->total ?? 0), $cases),
                'backgroundColor' => array_map(fn (AssetCondition $condition): string => self::color($condition), $cases),
                'borderWidth' => 0,
            ]],
            'labels' => array_map(fn (AssetCondition $condition): string => $condition->label(), $cases),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::doughnut($this->filter === 'value' ? 'rupiah' : 'int');
    }

    public static function color(AssetCondition $condition): string
    {
        return match ($condition) {
            AssetCondition::Available => ChartPalette::SUCCESS,
            AssetCondition::Transferred => ChartPalette::WARNING,
            AssetCondition::Sold => ChartPalette::GRAY,
            AssetCondition::Lost => ChartPalette::DANGER,
            AssetCondition::Damaged => ChartPalette::ORANGE,
        };
    }
}
