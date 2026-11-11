<?php

namespace App\Services;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestRecapStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Models\Area;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Rekap per Divisi (sumo: RequestExport): pivot Barang x Divisi untuk satu
 * area, satu tipe pengajuan, dan rentang tanggal pengajuan. Jumlah memakai
 * qty_approved bila sudah diisi, kalau belum qty_request; divisi diambil dari
 * divisi pemohon saat ini. Baris barang adalah barang yang muncul pada pengajuan
 * yang cocok (sumo memakai kategori barang yang id-nya kebetulan sama dengan id
 * tipe pengajuan; di GAIS keduanya tidak terikat).
 *
 * Hanya pengajuan yang boleh dilihat $viewer (aturan ItemRequestResource) yang
 * dihitung, jadi angkanya sama dengan yang tampil di daftar.
 */
final class ItemRequestRecapExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithTitle
{
    public const TOTAL_ITEMS_LABEL = 'Total Item per Divisi';

    public const TOTAL_COST_LABEL = 'Total Biaya per Divisi';

    /** @var Collection<int, Division>|null */
    private ?Collection $divisions = null;

    /** @var Collection<int, Product>|null */
    private ?Collection $products = null;

    /** @var array<string, float>|null kunci "product_id:division_id" */
    private ?array $totals = null;

    public function __construct(
        private readonly User $viewer,
        private readonly Area $area,
        private readonly ItemRequestType $type,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $until,
        private readonly ItemRequestRecapStatus $status = ItemRequestRecapStatus::NotCancelled,
    ) {}

    public function title(): string
    {
        return 'Rekap per Divisi';
    }

    public function fileName(): string
    {
        return sprintf(
            'rekap-pengajuan-%s-%s-%s-%s.xlsx',
            Str::slug($this->type->name) ?: 'tipe',
            Str::slug($this->area->name) ?: 'area',
            $this->from->format('Ymd'),
            $this->until->format('Ymd'),
        );
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Barang',
            'Tipe Unit',
            'Harga',
            ...$this->divisions()->pluck('name')->all(),
            'Total Item',
            'Total Biaya',
        ];
    }

    /**
     * Satu baris per barang, lalu dua baris footer total per divisi.
     *
     * @return list<list<int|float|string|null>>
     */
    public function array(): array
    {
        $divisions = $this->divisions();
        $totals = $this->totals();

        $itemsByDivision = $divisions->mapWithKeys(fn (Division $division): array => [$division->id => 0.0])->all();
        $costByDivision = $itemsByDivision;
        $rows = [];

        foreach ($this->products() as $product) {
            $price = (float) $product->price;
            $quantities = [];

            foreach ($divisions as $division) {
                $qty = $totals["{$product->id}:{$division->id}"] ?? 0.0;
                $quantities[] = $qty;
                $itemsByDivision[$division->id] += $qty;
                $costByDivision[$division->id] += $qty * $price;
            }

            $totalItems = array_sum($quantities);

            $rows[] = [
                $product->name,
                $product->unitType?->name,
                self::number($price),
                ...array_map(self::number(...), $quantities),
                self::number($totalItems),
                self::number($totalItems * $price),
            ];
        }

        $rows[] = [
            self::TOTAL_ITEMS_LABEL,
            null,
            null,
            ...array_map(self::number(...), array_values($itemsByDivision)),
            self::number(array_sum($itemsByDivision)),
            null,
        ];

        $rows[] = [
            self::TOTAL_COST_LABEL,
            null,
            null,
            ...array_map(self::number(...), array_values($costByDivision)),
            null,
            self::number(array_sum($costByDivision)),
        ];

        return $rows;
    }

    /**
     * Divisi aktif (belum dihapus) di area ini, urut nama.
     *
     * @return Collection<int, Division>
     */
    public function divisions(): Collection
    {
        return $this->divisions ??= Division::query()
            ->where('area_id', $this->area->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * Barang yang muncul pada pengajuan yang cocok, urut nama. Barang yang sudah
     * dihapus tetap ikut karena jumlahnya sudah tercatat.
     *
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        if ($this->products !== null) {
            return $this->products;
        }

        $productIds = collect(array_keys($this->totals()))
            ->map(fn (string $key): int => (int) Str::before($key, ':'))
            ->unique()
            ->all();

        return $this->products = Product::withTrashed()
            ->with('unitType')
            ->whereKey($productIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * SUM(COALESCE(qty_approved, qty_request)) per barang per divisi pemohon.
     *
     * @return array<string, float>
     */
    public function totals(): array
    {
        if ($this->totals !== null) {
            return $this->totals;
        }

        $rows = $this->applyStatusFilter($this->matchingRequests())
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->join('users', 'users.id', '=', 'item_requests.user_id')
            ->join('divisions', 'divisions.id', '=', 'users.division_id')
            ->where('divisions.area_id', $this->area->id)
            ->whereNull('divisions.deleted_at')
            ->whereNull('item_request_details.deleted_at')
            ->whereNotNull('item_request_details.product_id')
            ->toBase()
            ->selectRaw('item_request_details.product_id, divisions.id AS division_id, SUM(COALESCE(item_request_details.qty_approved, item_request_details.qty_request)) AS total_qty')
            ->groupBy('item_request_details.product_id', 'divisions.id')
            ->get();

        $totals = [];

        foreach ($rows as $row) {
            $totals["{$row->product_id}:{$row->division_id}"] = (float) $row->total_qty;
        }

        return $this->totals = $totals;
    }

    /**
     * Pengajuan tipe ini dalam rentang tanggal yang boleh dilihat viewer
     * (visibilitas, badan usaha, dan soft delete ikut aturan resource).
     *
     * @return Builder<ItemRequest>
     */
    private function matchingRequests(): Builder
    {
        $query = ItemRequestResource::scopeToViewer(ItemRequest::query(), $this->viewer);

        return $query
            ->where('item_requests.item_request_type_id', $this->type->id)
            ->whereBetween('item_requests.requested_at', [
                $this->from->copy()->startOfDay(),
                $this->until->copy()->endOfDay(),
            ]);
    }

    /**
     * Arti tiap pilihan sama dengan switch filter_request di sumo.
     *
     * @param  Builder<ItemRequest>  $query
     * @return Builder<ItemRequest>
     */
    private function applyStatusFilter(Builder $query): Builder
    {
        $notCancelled = fn (Builder $q): Builder => $q->where('item_requests.client_status', '!=', ItemRequestClientStatus::Cancelled->value);

        return match ($this->status) {
            ItemRequestRecapStatus::AwaitingExecutor => $this->whereExecutorApproval($notCancelled($query), approved: false),
            ItemRequestRecapStatus::ProcessedByExecutor => $this->whereExecutorApproval($notCancelled($query), approved: true),
            ItemRequestRecapStatus::NotCancelled => $notCancelled($query),
            ItemRequestRecapStatus::Cancelled => $query->where('item_requests.client_status', ItemRequestClientStatus::Cancelled->value),
            ItemRequestRecapStatus::Closed => $query->where('item_requests.client_status', ItemRequestClientStatus::Closed->value),
        };
    }

    /**
     * @param  Builder<ItemRequest>  $query
     * @return Builder<ItemRequest>
     */
    private function whereExecutorApproval(Builder $query, bool $approved): Builder
    {
        return $query->whereHas('approvals', fn (Builder $approvals): Builder => $approvals
            ->where('item_request_approvals.approval_type', ItemRequestApprovalType::Executor->value)
            ->when(
                $approved,
                fn (Builder $q): Builder => $q->whereNotNull('item_request_approvals.approved_by_user_id'),
                fn (Builder $q): Builder => $q->whereNull('item_request_approvals.approved_by_user_id'),
            ));
    }

    /**
     * Angka bulat ditulis tanpa desimal supaya sel Excel tidak menampilkan 3.0.
     */
    private static function number(float $value): int|float
    {
        $rounded = round($value, 2);

        return floor($rounded) === $rounded ? (int) $rounded : $rounded;
    }
}
