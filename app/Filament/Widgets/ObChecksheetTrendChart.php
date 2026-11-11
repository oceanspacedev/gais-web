<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ObChecksheetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ObChecksheet;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Dasbor > Operasional: tugas kebersihan per hari, 14 hari terakhir, selesai vs belum.
 */
class ObChecksheetTrendChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Kebersihan 14 Hari Terakhir';

    protected ?string $description = 'Tugas OB per tanggal jadwal: selesai dan belum selesai.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $ago): Carbon => today()->subDays($ago));

        $rows = ObChecksheetResource::getEloquentQuery()->toBase()
            ->whereDate('scheduled_date', '>=', $days->first()->toDateString())
            ->whereDate('scheduled_date', '<=', $days->last()->toDateString())
            ->selectRaw('DATE(scheduled_date) AS day, status, COUNT(*) AS total')
            ->groupBy('day', 'status')
            ->get();

        $count = fn (Carbon $day, bool $completed): int => (int) $rows
            ->filter(fn (object $row): bool => $row->day === $day->toDateString() && (($row->status === ObChecksheet::STATUS_COMPLETED) === $completed))
            ->sum('total');

        return [
            'datasets' => [
                [
                    'label' => 'Selesai',
                    'data' => $days->map(fn (Carbon $day): int => $count($day, true))->all(),
                    'backgroundColor' => ChartPalette::SUCCESS,
                ],
                [
                    'label' => 'Belum selesai',
                    'data' => $days->map(fn (Carbon $day): int => $count($day, false))->all(),
                    'backgroundColor' => ChartPalette::WARNING,
                ],
            ],
            'labels' => $days->map(fn (Carbon $day): string => $day->locale('id')->isoFormat('D MMM'))->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', stacked: true, legend: true);
    }
}
