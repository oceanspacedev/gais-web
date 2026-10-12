<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequestType;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Beranda sumo: "Jumlah Biaya Pengajuan" per pemohon (10 terbesar) =
 * jumlah item x harga barang, setahun terakhir.
 */
class ItemRequestCostChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 12;

    protected ?string $heading = 'Biaya Pengajuan per Pemohon';

    protected ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

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
            ->selectRaw('users.name AS requester, SUM(COALESCE(item_request_details.qty_approved, item_request_details.qty_request) * COALESCE(products.price, 0)) AS total')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Total biaya (Rp)',
                'data' => $rows->pluck('total')->map(fn ($value): float => round((float) $value))->all(),
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => $rows->pluck('requester')->all(),
        ];
    }

    protected function baseQuery(): Builder
    {
        $query = ItemRequestResource::getEloquentQuery()
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->leftJoin('products', 'products.id', '=', 'item_request_details.product_id')
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
