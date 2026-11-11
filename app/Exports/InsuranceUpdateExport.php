<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Imports\InsuranceUpdateImport;
use App\Models\Insurance;
use App\Models\InsuranceUpdate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor baris pembaharuan satu polis dengan kolom template `InsuranceUpdateImport`.
 */
class InsuranceUpdateExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function __construct(private Insurance $insurance) {}

    public function fileName(): string
    {
        return static::safeFileName('pembaharuan-asuransi', $this->insurance->policy_number, 'polis-'.$this->insurance->id);
    }

    /**
     * @return Collection<int, InsuranceUpdate>
     */
    public function collection(): Collection
    {
        return InsuranceUpdate::query()
            ->with(['stockProvider', 'buildingProvider'])
            ->where('insurance_id', $this->insurance->id)
            ->orderBy('join_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(InsuranceUpdateImport::sampleRow());
    }

    /**
     * @param  InsuranceUpdate  $update
     * @return list<mixed>
     */
    public function map($update): array
    {
        return $this->orderedByHeadings([
            'no_polis_induk' => $this->insurance->policy_number,
            'no_polis' => $update->policy_number,
            'asuransi_stok' => $update->stockProvider?->name,
            'nilai_stok' => $update->stock_worth,
            'nilai_aktual_stok' => $update->actual_stock_worth,
            'premi_stok' => $update->stock_premium,
            'asuransi_bangunan' => $update->buildingProvider?->name,
            'nilai_bangunan' => $update->building_worth,
            'premi_bangunan' => $update->building_premium,
            'tanggal_mulai' => $update->join_date?->format('Y-m-d'),
            'tanggal_akhir' => $update->expired_date?->format('Y-m-d'),
            'catatan' => $update->notes,
            'status' => $update->status?->value,
        ]);
    }
}
