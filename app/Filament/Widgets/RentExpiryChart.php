<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RentResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Rent;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Asuransi & Sewa: kontrak sewa yang berakhir per bulan dalam 12 bulan
 * ke depan beserta nilai sewa per tahunnya, untuk merencanakan perpanjangan.
 */
class RentExpiryChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Sewa Berakhir 12 Bulan ke Depan';

    protected ?string $description = 'Kontrak berjalan yang berakhir di bulan itu (perpanjangan terakhir bila ada) dan nilai sewa per tahunnya.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 6;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = MonthSeries::upcoming(12);
        $start = now()->startOfMonth();
        $end = now()->startOfMonth()->addMonths(11)->endOfMonth();

        $rents = RentResource::getEloquentQuery()->activeContracts()->with('updates')->get()
            ->filter(function (Rent $rent) use ($start, $end): bool {
                $expiry = $rent->effectiveExpiredDate();

                return $expiry !== null && $expiry->betweenIncluded($start, $end);
            })
            ->groupBy(fn (Rent $rent): string => $rent->effectiveExpiredDate()->format('Y-m'));

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Kontrak berakhir',
                    'data' => MonthSeries::fill($months, $rents->map(fn ($group): int => $group->count())),
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::ORANGE, 0.75),
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => 'Nilai sewa per tahun',
                    'data' => MonthSeries::fill($months, $rents->map(fn ($group): float => (float) $group->sum(fn (Rent $rent): int => (int) $rent->effective('rent_per_year'))), integer: false),
                    'borderColor' => ChartPalette::PRIMARY,
                    'backgroundColor' => ChartPalette::alpha(ChartPalette::PRIMARY, 0.15),
                    'tension' => 0.3,
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 1,
                ],
            ],
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::combo('int', 'rupiah');
    }
}
