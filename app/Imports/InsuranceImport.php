<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesLegacyCells;
use App\Models\Insurance;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor polis asuransi dengan kolom template sumo. Baris dengan `id` yang ada
 * memperbarui polis itu; tanpa id, nomor polis yang sama diperbarui.
 */
class InsuranceImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use ParsesLegacyCells;

    /**
     * @return array<string, mixed>
     */
    public static function sampleRow(): array
    {
        return [
            'id' => '',
            'no_polis' => 'POL-2026-001',
            'alamat_tertanggung' => 'Jl. Contoh No. 1',
            'nama_tertanggung' => 'PT Contoh',
            'kode_gudang' => 'GD-01',
            'detail_asuransi' => 'Gudang dan stok',
            'alamat_yang_diasuransikan' => 'Jl. Gudang No. 2',
            'asuransi_stok' => 'Asuransi A',
            'nilai_stok' => 150000000,
            'nilai_aktual_stok' => 140000000,
            'premi_stok' => 450000,
            'asuransi_bangunan' => 'Asuransi B',
            'nilai_bangunan' => 500000000,
            'premi_bangunan' => 900000,
            'kategori_asuransi' => 'GUDANG',
            'tanggal_mulai' => '01/01/2026',
            'tanggal_akhir' => '31/12/2026',
            'cakupan_asuransi' => 'ALL RISK',
            'status' => 'BERJALAN',
            'catatan' => '',
        ];
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $policyNumber = $this->text($row['no_polis'] ?? null);

                if ($policyNumber === null) {
                    continue;
                }

                $attributes = [
                    'policy_number' => $policyNumber,
                    'insured_name' => $this->text($row['nama_tertanggung'] ?? null) ?? '-',
                    'insured_address' => $this->text($row['alamat_tertanggung'] ?? null),
                    'warehouse_code' => $this->text($row['kode_gudang'] ?? null),
                    'insured_detail' => $this->text($row['detail_asuransi'] ?? null),
                    'risk_address' => $this->text($row['alamat_yang_diasuransikan'] ?? null),
                    'stock_provider_id' => $this->provider($row['asuransi_stok'] ?? null),
                    'stock_worth' => $this->money($row['nilai_stok'] ?? null),
                    'actual_stock_worth' => $this->money($row['nilai_aktual_stok'] ?? null),
                    'stock_premium' => $this->money($row['premi_stok'] ?? null),
                    'building_provider_id' => $this->provider($row['asuransi_bangunan'] ?? null),
                    'building_worth' => $this->money($row['nilai_bangunan'] ?? null),
                    'building_premium' => $this->money($row['premi_bangunan'] ?? null),
                    'insurance_category_id' => $this->named(InsuranceCategory::class, $row['kategori_asuransi'] ?? null),
                    'insurance_scope_id' => $this->named(InsuranceScope::class, $row['cakupan_asuransi'] ?? null),
                    'join_date' => $this->date($row['tanggal_mulai'] ?? null),
                    'expired_date' => $this->date($row['tanggal_akhir'] ?? null),
                    'status' => $this->status($row['status'] ?? null),
                    'notes' => $this->text($row['catatan'] ?? null),
                    'user_id' => auth()->id(),
                ];

                $id = $this->text($row['id'] ?? null);
                $existing = $id !== null && is_numeric($id)
                    ? Insurance::query()->find((int) $id)
                    : Insurance::query()->where('policy_number', $policyNumber)->first();

                $existing === null
                    ? Insurance::query()->create($attributes)
                    : $existing->fill($attributes)->save();
            }
        });
    }

    public function chunkSize(): int
    {
        return 250;
    }

    private function provider(mixed $name): ?int
    {
        return $this->named(InsuranceProvider::class, $name);
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function named(string $model, mixed $name): ?int
    {
        $name = $this->text($name);

        return $name === null ? null : $model::query()->firstOrCreate(['name' => $name])->id;
    }
}
