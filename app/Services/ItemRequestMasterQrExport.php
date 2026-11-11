<?php

namespace App\Services;

use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Ekspor Master QR (sumo: RequestMasterQRExport): satu baris per detail
 * pengajuan dengan id detail (isi QR label, sama dengan cetak QR per pengajuan),
 * nama barang, dan pemohon, untuk satu tipe pengajuan dan rentang tanggal.
 * Dipakai mencetak label QR massal di luar aplikasi. Seperti di sumo, semua
 * status ikut (termasuk yang dibatalkan); yang dihitung hanya pengajuan yang
 * boleh dilihat $viewer.
 */
final class ItemRequestMasterQrExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison, WithTitle
{
    public function __construct(
        private readonly User $viewer,
        private readonly ItemRequestType $type,
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $until,
    ) {}

    public function title(): string
    {
        return 'Master QR';
    }

    public function fileName(): string
    {
        return sprintf(
            'master-qr-pengajuan-%s-%s-%s.xlsx',
            Str::slug($this->type->name) ?: 'tipe',
            $this->from->format('Ymd'),
            $this->until->format('Ymd'),
        );
    }

    /**
     * Nama kolom sengaja sama dengan ekspor sumo karena template cetak QR
     * membacanya.
     *
     * @return list<string>
     */
    public function headings(): array
    {
        return ['id', 'nama_barang', 'pemohon'];
    }

    /**
     * @return list<array{0: int, 1: string, 2: string}>
     */
    public function array(): array
    {
        $rows = ItemRequestResource::scopeToViewer(ItemRequest::query(), $this->viewer)
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->leftJoin('products', 'products.id', '=', 'item_request_details.product_id')
            ->leftJoin('users', 'users.id', '=', 'item_requests.user_id')
            ->where('item_requests.item_request_type_id', $this->type->id)
            ->whereBetween('item_requests.requested_at', [
                $this->from->copy()->startOfDay(),
                $this->until->copy()->endOfDay(),
            ])
            ->whereNull('item_request_details.deleted_at')
            ->orderBy('item_requests.id')
            ->orderBy('item_request_details.id')
            ->toBase()
            ->select([
                'item_request_details.id AS detail_id',
                'products.name AS product_name',
                'users.name AS requester_name',
            ])
            ->get();

        return $rows
            ->map(fn (object $row): array => [
                (int) $row->detail_id,
                (string) ($row->product_name ?? ''),
                (string) ($row->requester_name ?? ''),
            ])
            ->all();
    }
}
