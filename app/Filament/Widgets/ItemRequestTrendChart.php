<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ItemRequestType;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

/**
 * Dasbor > Pengajuan Barang: pengajuan per bulan, ditumpuk per hasil akhir
 * (diterima, masih terbuka, dibatalkan), bisa disaring per tipe.
 */
class ItemRequestTrendChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Tren Pengajuan per Bulan';

    protected ?string $description = 'Jumlah pengajuan 12 bulan terakhir menurut hasil akhirnya.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 8;

    public ?string $filter = 'all';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getFilters(): ?array
    {
        return ['all' => 'Semua tipe'] + ItemRequestType::query()->orderBy('id')->pluck('name', 'id')->all();
    }

    protected function getData(): array
    {
        $months = MonthSeries::past(12);

        $query = ItemRequestResource::getEloquentQuery()->toBase()
            ->where('item_requests.requested_at', '>=', MonthSeries::startOfPast(12));

        if (filled($this->filter) && $this->filter !== 'all') {
            $query->where('item_requests.item_request_type_id', (int) $this->filter);
        }

        $rows = $query
            ->selectRaw(MonthSeries::sqlKey('item_requests.requested_at').' AS period, client_status, COUNT(*) AS total')
            ->groupBy('period', 'client_status')
            ->get();

        $bucket = fn (array $statuses) => $rows
            ->whereIn('client_status', $statuses)
            ->groupBy('period')
            ->map(fn ($group): int => (int) $group->sum('total'));

        return [
            'datasets' => [
                [
                    'label' => 'Diterima',
                    'data' => MonthSeries::fill($months, $bucket([ItemRequestClientStatus::Accepted->value])),
                    'backgroundColor' => ChartPalette::SUCCESS,
                ],
                [
                    'label' => 'Masih terbuka',
                    'data' => MonthSeries::fill($months, $bucket([ItemRequestClientStatus::Waiting->value, ItemRequestClientStatus::Processed->value, ItemRequestClientStatus::Closed->value])),
                    'backgroundColor' => ChartPalette::WARNING,
                ],
                [
                    'label' => 'Dibatalkan',
                    'data' => MonthSeries::fill($months, $bucket([ItemRequestClientStatus::Cancelled->value])),
                    'backgroundColor' => ChartPalette::DANGER,
                ],
            ],
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', stacked: true, legend: true);
    }
}
