<?php

namespace App\Imports;

use App\Enums\ContractStatus;
use App\Imports\Concerns\ImportsRenewalRows;
use App\Models\Insurance;
use App\Models\InsuranceProvider;
use App\Models\InsuranceUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor baris pembaharuan satu polis dengan kolom template sumo (importUpdate).
 * `no_polis_induk` harus sama dengan nomor polis yang dibuka; `no_polis` yang
 * sudah ada pada polis itu diperbarui, selebihnya dibuat dengan status BERJALAN.
 * Sel kosong tidak mengubah baris yang sudah ada. Satu kesalahan membatalkan
 * seluruh file.
 */
class InsuranceUpdateImport implements ToCollection, WithHeadingRow
{
    use ImportsRenewalRows;

    private Insurance $insurance;

    /**
     * ExcelImportAction membuat importer sebagai (model, atribut `use()`, data
     * tambahan); polis induk dikirim sebagai atribut pertama `use()`.
     *
     * @param  array<int, Insurance|int|string>  $attributes
     * @param  array<string, mixed>  $additionalData
     */
    public function __construct(mixed $model = null, array $attributes = [], array $additionalData = [])
    {
        $insurance = $attributes[0] ?? null;

        $this->insurance = $insurance instanceof Insurance ? $insurance : Insurance::query()->findOrFail($insurance);
    }

    public static function for(Insurance $insurance): self
    {
        return new self(attributes: [$insurance]);
    }

    /**
     * Baris contoh template; kuncinya adalah kolom template sumo ditambah `status`.
     *
     * @return array<string, mixed>
     */
    public static function sampleRow(?Insurance $insurance = null): array
    {
        return [
            'no_polis_induk' => $insurance?->policy_number ?? 'POL-2026-001',
            'no_polis' => 'POL-2027-001',
            'asuransi_stok' => 'Asuransi A',
            'nilai_stok' => 150000000,
            'nilai_aktual_stok' => 140000000,
            'premi_stok' => 450000,
            'asuransi_bangunan' => 'Asuransi B',
            'nilai_bangunan' => 500000000,
            'premi_bangunan' => 900000,
            'tanggal_mulai' => '2027-01-01',
            'tanggal_akhir' => '2027-12-31',
            'catatan' => '',
            'status' => ContractStatus::Running->value,
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

                if (! $this->matchesParent($row['no_polis_induk'] ?? null, $this->insurance->policy_number)) {
                    $this->reject($number, sprintf(
                        'no_polis_induk "%s" tidak sama dengan polis %s yang sedang dibuka.',
                        $this->text($row['no_polis_induk'] ?? null) ?? '',
                        $this->insurance->policy_number,
                    ));
                }

                $policyNumber = $this->text($row['no_polis'] ?? null) ?? $this->reject($number, 'kolom no_polis wajib diisi.');

                $update = InsuranceUpdate::query()
                    ->where('insurance_id', $this->insurance->id)
                    ->where('policy_number', $policyNumber)
                    ->first() ?? new InsuranceUpdate(['insurance_id' => $this->insurance->id, 'policy_number' => $policyNumber]);
                $isNew = ! $update->exists;

                $update->fill([
                    'stock_provider_id' => $this->cell($row, 'asuransi_stok', fn ($value) => $this->provider($value), $update->stock_provider_id),
                    'stock_worth' => $this->cell($row, 'nilai_stok', fn ($value) => $this->money($value), $update->stock_worth),
                    'actual_stock_worth' => $this->cell($row, 'nilai_aktual_stok', fn ($value) => $this->money($value), $update->actual_stock_worth),
                    'stock_premium' => $this->cell($row, 'premi_stok', fn ($value) => $this->money($value), $update->stock_premium),
                    'building_provider_id' => $this->cell($row, 'asuransi_bangunan', fn ($value) => $this->provider($value), $update->building_provider_id),
                    'building_worth' => $this->cell($row, 'nilai_bangunan', fn ($value) => $this->money($value), $update->building_worth),
                    'building_premium' => $this->cell($row, 'premi_bangunan', fn ($value) => $this->money($value), $update->building_premium),
                    'join_date' => $this->dateCell($row, 'tanggal_mulai', $number, $isNew, $update->join_date),
                    'expired_date' => $this->dateCell($row, 'tanggal_akhir', $number, $isNew, $update->expired_date),
                    'status' => $this->cell($row, 'status', fn ($value) => $this->status($value), $isNew ? ContractStatus::Running->value : $update->status),
                    'notes' => $this->cell($row, 'catatan', fn ($value) => $this->text($value), $update->notes),
                    'user_id' => auth()->id(),
                ])->save();
            }
        });
    }

    private function provider(mixed $name): ?int
    {
        $name = $this->text($name);

        return $name === null ? null : InsuranceProvider::query()->firstOrCreate(['name' => $name])->id;
    }
}
