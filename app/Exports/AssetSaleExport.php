<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Filament\Resources\AssetSaleResource;
use App\Imports\AssetSaleImport;
use App\Models\AssetSale;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor riwayat penjualan antar badan usaha yang boleh dilihat pembaca, urut
 * kronologis. Kolom template AssetSaleImport ditaruh di depan supaya mudah
 * dicocokkan dengan file impor, ditambah id penjualan, pencatat, dan waktunya.
 */
class AssetSaleExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function fileName(): string
    {
        return 'penjualan-aset.xlsx';
    }

    /**
     * @return Collection<int, AssetSale>
     */
    public function collection(): Collection
    {
        return AssetSaleResource::getEloquentQuery()
            ->with('asset.attributes')
            ->orderBy('asset_sales.sold_at')
            ->orderBy('asset_sales.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [...array_keys(AssetSaleImport::sampleRow()), 'id_penjualan', 'dicatat_oleh', 'dicatat_pada'];
    }

    /**
     * @param  AssetSale  $sale
     * @return list<mixed>
     */
    public function map($sale): array
    {
        return $this->orderedByHeadings([
            'id_aset' => $sale->asset_id,
            'serial_number' => $sale->asset?->serial_number,
            'nama_aset' => $sale->asset?->name,
            'badan_usaha_penjual' => $sale->fromBusinessEntity?->name,
            'badan_usaha_pembeli' => $sale->toBusinessEntity?->name,
            'tanggal_penjualan' => $sale->sold_at?->format('Y-m-d'),
            'harga_jual' => $sale->sold_price,
            'catatan' => $sale->notes,
            'id_penjualan' => $sale->id,
            'dicatat_oleh' => $sale->createdBy?->name,
            'dicatat_pada' => $sale->created_at?->format('Y-m-d H:i:s'),
        ]);
    }
}
