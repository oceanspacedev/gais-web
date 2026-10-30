<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Filament\Resources\InsuranceResource;
use App\Imports\InsuranceImport;
use App\Models\Insurance;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor polis asuransi yang boleh dilihat pembaca dengan kolom template
 * `InsuranceImport` termasuk `id` (sumo: /insurance/export), jadi hasilnya bisa
 * diubah lalu diimpor kembali untuk memperbarui polis yang sama.
 */
class InsuranceExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function fileName(): string
    {
        return 'asuransi.xlsx';
    }

    /**
     * @return Collection<int, Insurance>
     */
    public function collection(): Collection
    {
        return InsuranceResource::getEloquentQuery()
            ->with(['stockProvider', 'buildingProvider', 'category', 'scope'])
            ->orderBy('insurances.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(InsuranceImport::sampleRow());
    }

    /**
     * @param  Insurance  $insurance
     * @return list<mixed>
     */
    public function map($insurance): array
    {
        return $this->orderedByHeadings([
            'id' => $insurance->id,
            'no_polis' => $insurance->policy_number,
            'alamat_tertanggung' => $insurance->insured_address,
            'nama_tertanggung' => $insurance->insured_name,
            'kode_gudang' => $insurance->warehouse_code,
            'detail_asuransi' => $insurance->insured_detail,
            'alamat_yang_diasuransikan' => $insurance->risk_address,
            'asuransi_stok' => $insurance->stockProvider?->name,
            'nilai_stok' => $insurance->stock_worth,
            'nilai_aktual_stok' => $insurance->actual_stock_worth,
            'premi_stok' => $insurance->stock_premium,
            'asuransi_bangunan' => $insurance->buildingProvider?->name,
            'nilai_bangunan' => $insurance->building_worth,
            'premi_bangunan' => $insurance->building_premium,
            'kategori_asuransi' => $insurance->category?->name,
            'tanggal_mulai' => $insurance->join_date?->format('Y-m-d'),
            'tanggal_akhir' => $insurance->expired_date?->format('Y-m-d'),
            'cakupan_asuransi' => $insurance->scope?->name,
            'status' => $insurance->status?->value,
            'catatan' => $insurance->notes,
        ]);
    }
}
