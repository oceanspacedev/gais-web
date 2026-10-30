<?php

namespace App\Imports;

use App\Enums\ContractStatus;
use App\Imports\Concerns\ImportsRenewalRows;
use App\Models\Rent;
use App\Models\RentUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor baris perpanjangan satu perjanjian sewa dengan kolom template sumo
 * (importUpdate). `kode_sewa_induk` harus sama dengan kode sewa yang dibuka;
 * `kode` yang sudah ada pada sewa itu diperbarui, selebihnya dibuat dengan status
 * BERJALAN dan kode RENTUP#### bila `kode` kosong. Sel kosong tidak mengubah
 * baris yang sudah ada. Satu kesalahan membatalkan seluruh file.
 */
class RentUpdateImport implements ToCollection, WithHeadingRow
{
    use ImportsRenewalRows;

    private Rent $rent;

    /**
     * ExcelImportAction membuat importer sebagai (model, atribut `use()`, data
     * tambahan); sewa induk dikirim sebagai atribut pertama `use()`.
     *
     * @param  array<int, Rent|int|string>  $attributes
     * @param  array<string, mixed>  $additionalData
     */
    public function __construct(mixed $model = null, array $attributes = [], array $additionalData = [])
    {
        $rent = $attributes[0] ?? null;

        $this->rent = $rent instanceof Rent ? $rent : Rent::query()->findOrFail($rent);
    }

    public static function for(Rent $rent): self
    {
        return new self(attributes: [$rent]);
    }

    /**
     * Baris contoh template; kuncinya adalah kolom template sumo.
     *
     * @return array<string, mixed>
     */
    public static function sampleRow(?Rent $rent = null): array
    {
        return [
            'kode_sewa_induk' => $rent?->rent_code ?? 'RENT0001',
            'kode' => '',
            'pihak_pertama' => $rent?->first_party ?? 'PT Contoh',
            'pihak_kedua' => $rent?->second_party ?? 'Bapak Pemilik',
            'sewa_per_tahun' => 12000000,
            'dana_cvcs' => 12000000,
            'dana_online' => 0,
            'tanggal_mulai' => '2027-01-01',
            'tanggal_akhir' => '2027-12-31',
            'bukti_potong' => 'ADA',
            'berkas' => 'ADA',
            'status' => ContractStatus::Running->value,
            'reminder_bulan_sebelumnya' => '1',
            'catatan' => '',
        ];
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $index => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $number = $this->rowNumber($index);

                if (! $this->matchesParent($row['kode_sewa_induk'] ?? null, $this->rent->rent_code)) {
                    $this->reject($number, sprintf(
                        'kode_sewa_induk "%s" tidak sama dengan sewa %s yang sedang dibuka.',
                        $this->text($row['kode_sewa_induk'] ?? null) ?? '',
                        $this->rent->rent_code,
                    ));
                }

                $code = $this->text($row['kode'] ?? null);
                $update = $code === null ? null : RentUpdate::query()
                    ->where('rent_id', $this->rent->id)
                    ->where('rent_code', $code)
                    ->first();
                $update ??= new RentUpdate(['rent_id' => $this->rent->id, 'rent_code' => $code ?? RentUpdate::nextCode()]);
                $isNew = ! $update->exists;

                $update->fill([
                    'first_party' => $this->cell($row, 'pihak_pertama', fn ($value) => $this->text($value), $isNew ? $this->rent->first_party : $update->first_party),
                    'second_party' => $this->cell($row, 'pihak_kedua', fn ($value) => $this->text($value), $isNew ? $this->rent->second_party : $update->second_party),
                    'rent_per_year' => $this->cell($row, 'sewa_per_tahun', fn ($value) => $this->money($value), $update->rent_per_year),
                    'cvcs_fund' => $this->cell($row, 'dana_cvcs', fn ($value) => $this->money($value), $update->cvcs_fund),
                    'online_fund' => $this->cell($row, 'dana_online', fn ($value) => $this->money($value), $update->online_fund),
                    'join_date' => $this->dateCell($row, 'tanggal_mulai', $number, $isNew, $update->join_date),
                    'expired_date' => $this->dateCell($row, 'tanggal_akhir', $number, $isNew, $update->expired_date),
                    'deduction_evidence' => $this->cell($row, 'bukti_potong', fn ($value) => $this->text($value), $update->deduction_evidence),
                    'document' => $this->cell($row, 'berkas', fn ($value) => $this->text($value), $update->document),
                    'status' => $this->cell($row, 'status', fn ($value) => $this->status($value), $isNew ? ContractStatus::Running->value : $update->status),
                    'month_before_reminder' => $this->cell($row, 'reminder_bulan_sebelumnya', fn ($value) => $this->text($value), $update->month_before_reminder),
                    'notes' => $this->cell($row, 'catatan', fn ($value) => $this->text($value), $update->notes),
                    'user_id' => auth()->id(),
                ])->save();
            }
        });
    }
}
