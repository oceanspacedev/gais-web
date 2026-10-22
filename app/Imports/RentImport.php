<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesLegacyCells;
use App\Models\Rent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor perjanjian sewa dengan kolom template sumo. Kode sewa yang sama diperbarui.
 */
class RentImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use ParsesLegacyCells;

    /**
     * @return array<string, mixed>
     */
    public static function sampleRow(): array
    {
        return [
            'kode' => 'RENT-2026-001',
            'alamat_bangunan' => 'Jl. Contoh No. 1',
            'nama_bangunan' => 'Ruko Contoh',
            'pihak_pertama' => 'PT Contoh',
            'pihak_kedua' => 'Bapak Pemilik',
            'sewa_per_tahun' => 12000000,
            'dana_cvcs' => 12000000,
            'dana_online' => 0,
            'tanggal_mulai' => '01/01/2026',
            'tanggal_akhir' => '31/12/2026',
            'bukti_potong' => 'ADA',
            'berkas' => 'ADA',
            'status' => 'BERJALAN',
            'reminder_bulan_sebelumnya' => '1',
            'catatan' => '',
        ];
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $code = $this->text($row['kode'] ?? null);

                if ($code === null) {
                    continue;
                }

                Rent::query()->updateOrCreate(['rent_code' => $code], [
                    'rented_address' => $this->text($row['alamat_bangunan'] ?? null),
                    'rented_detail' => $this->text($row['nama_bangunan'] ?? null) ?? '-',
                    'first_party' => $this->text($row['pihak_pertama'] ?? null) ?? '-',
                    'second_party' => $this->text($row['pihak_kedua'] ?? null) ?? '-',
                    'rent_per_year' => $this->money($row['sewa_per_tahun'] ?? null),
                    'cvcs_fund' => $this->money($row['dana_cvcs'] ?? null),
                    'online_fund' => $this->money($row['dana_online'] ?? null),
                    'join_date' => $this->date($row['tanggal_mulai'] ?? null),
                    'expired_date' => $this->date($row['tanggal_akhir'] ?? null),
                    'deduction_evidence' => $this->text($row['bukti_potong'] ?? null),
                    'document' => $this->text($row['berkas'] ?? null),
                    'status' => $this->status($row['status'] ?? null),
                    'month_before_reminder' => $this->text($row['reminder_bulan_sebelumnya'] ?? null),
                    'notes' => $this->text($row['catatan'] ?? null),
                    'user_id' => auth()->id(),
                ]);
            }
        });
    }

    public function chunkSize(): int
    {
        return 250;
    }
}
