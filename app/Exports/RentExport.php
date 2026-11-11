<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Filament\Resources\RentResource;
use App\Imports\RentImport;
use App\Models\Rent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor perjanjian sewa yang boleh dilihat pembaca dengan kolom template
 * `RentImport` (sumo: /rent/export), jadi hasilnya bisa diubah lalu diimpor kembali.
 */
class RentExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function fileName(): string
    {
        return 'sewa.xlsx';
    }

    /**
     * @return Collection<int, Rent>
     */
    public function collection(): Collection
    {
        return RentResource::getEloquentQuery()->orderBy('rents.id')->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(RentImport::sampleRow());
    }

    /**
     * @param  Rent  $rent
     * @return list<mixed>
     */
    public function map($rent): array
    {
        return $this->orderedByHeadings([
            'kode' => $rent->rent_code,
            'alamat_bangunan' => $rent->rented_address,
            'nama_bangunan' => $rent->rented_detail,
            'pihak_pertama' => $rent->first_party,
            'pihak_kedua' => $rent->second_party,
            'sewa_per_tahun' => $rent->rent_per_year,
            'dana_cvcs' => $rent->cvcs_fund,
            'dana_online' => $rent->online_fund,
            'tanggal_mulai' => $rent->join_date?->format('Y-m-d'),
            'tanggal_akhir' => $rent->expired_date?->format('Y-m-d'),
            'bukti_potong' => $rent->deduction_evidence,
            'berkas' => $rent->document,
            'status' => $rent->status?->value,
            'reminder_bulan_sebelumnya' => $rent->month_before_reminder,
            'catatan' => $rent->notes,
        ]);
    }
}
