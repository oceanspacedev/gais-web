<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ItemRequestType;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasbor > Pengajuan Barang (sumo: "Jumlah Item Pengajuan"): item per pemohon
 * (10 terbanyak) untuk tipe yang dipilih; jumlah memakai qty disetujui bila ada,
 * kalau tidak qty diminta.
 */
class ItemRequestItemsChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Item Pengajuan per Pemohon';

    protected ?string $description = '10 pemohon dengan item terbanyak, 12 bulan terakhir.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

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
        $rows = $this->baseQuery()
            ->selectRaw('users.name AS requester, SUM(COALESCE(item_request_details.qty_approved, item_request_details.qty_request)) AS total')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Total item',
                'data' => $rows->pluck('total')->map(fn ($value): float => (float) $value)->all(),
                'backgroundColor' => ChartPalette::PRIMARY,
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('requester')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', horizontal: true);
    }

    protected function baseQuery(): Builder
    {
        $query = ItemRequestResource::getEloquentQuery()
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->join('users', 'users.id', '=', 'item_requests.user_id')
            ->whereNull('item_request_details.deleted_at')
            ->where('item_requests.client_status', '<>', ItemRequestClientStatus::Cancelled->value)
            ->where('item_requests.requested_at', '>=', now()->subYear());

        if (filled($this->filter) && $this->filter !== 'all') {
            $query->where('item_requests.item_request_type_id', (int) $this->filter);
        }

        return $query;
    }
}
