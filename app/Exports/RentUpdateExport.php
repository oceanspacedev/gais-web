<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsRenewalRows;
use App\Imports\RentUpdateImport;
use App\Models\Rent;
use App\Models\RentUpdate;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor baris perpanjangan satu perjanjian sewa dengan kolom template `RentUpdateImport`.
 */
class RentUpdateExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsRenewalRows;

    public function __construct(private Rent $rent) {}

    public function fileName(): string
    {
        return static::safeFileName('perpanjangan-sewa', $this->rent->rent_code, 'sewa-'.$this->rent->id);
    }

    /**
     * @return Collection<int, RentUpdate>
     */
    public function collection(): Collection
    {
        return RentUpdate::query()
            ->where('rent_id', $this->rent->id)
            ->orderBy('join_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(RentUpdateImport::sampleRow());
    }

    /**
     * @param  RentUpdate  $update
     * @return list<mixed>
     */
    public function map($update): array
    {
        return $this->orderedByHeadings([
            'kode_sewa_induk' => $this->rent->rent_code,
            'kode' => $update->rent_code,
            'pihak_pertama' => $update->first_party,
            'pihak_kedua' => $update->second_party,
            'sewa_per_tahun' => $update->rent_per_year,
            'dana_cvcs' => $update->cvcs_fund,
            'dana_online' => $update->online_fund,
            'tanggal_mulai' => $update->join_date?->format('Y-m-d'),
            'tanggal_akhir' => $update->expired_date?->format('Y-m-d'),
            'bukti_potong' => $update->deduction_evidence,
            'berkas' => $update->document,
            'status' => $update->status?->value,
            'reminder_bulan_sebelumnya' => $update->month_before_reminder,
            'catatan' => $update->notes,
        ]);
    }
}
