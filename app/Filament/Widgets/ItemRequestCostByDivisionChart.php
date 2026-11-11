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

/**
 * Dasbor > Pengajuan Barang: biaya pengajuan setahun terakhir per divisi
 * pemohon (10 terbesar), pelengkap grafik per pemohon dan Rekap per Divisi.
 */
class ItemRequestCostByDivisionChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Biaya Pengajuan per Divisi';

    protected ?string $description = '10 divisi dengan biaya terbesar, 12 bulan terakhir (jumlah disetujui × harga barang).';

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
        $query = ItemRequestResource::getEloquentQuery()->toBase()
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->leftJoin('products', 'products.id', '=', 'item_request_details.product_id')
            ->join('users', 'users.id', '=', 'item_requests.user_id')
            ->leftJoin('divisions', 'divisions.id', '=', 'users.division_id')
            ->whereNull('item_request_details.deleted_at')
            ->where('item_requests.client_status', '<>', ItemRequestClientStatus::Cancelled->value)
            ->where('item_requests.requested_at', '>=', now()->subYear());

        if (filled($this->filter) && $this->filter !== 'all') {
            $query->where('item_requests.item_request_type_id', (int) $this->filter);
        }

        $rows = $query
            ->selectRaw("COALESCE(divisions.name, 'Tanpa divisi') AS division, SUM(COALESCE(item_request_details.qty_approved, item_request_details.qty_request) * COALESCE(products.price, 0)) AS total")
            ->groupBy('division')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Total biaya',
                'data' => $rows->pluck('total')->map(fn ($value): float => round((float) $value))->all(),
                'backgroundColor' => ChartPalette::TEAL,
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('division')->all(),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('rupiah', horizontal: true);
    }
}
