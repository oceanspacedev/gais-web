<?php

namespace App\Imports;

use App\Imports\Concerns\ParsesLegacyCells;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Impor barang (sumo: template nama_barang, kategori, tipe_unit, harga,
 * keterangan, stok). Barang dengan nama yang sama diperbarui, bukan digandakan.
 */
class ProductImport implements ToCollection, WithChunkReading, WithHeadingRow
{
    use ParsesLegacyCells;

    /**
     * @return array<string, mixed>
     */
    public static function sampleRow(): array
    {
        return [
            'nama_barang' => 'Pulpen Standard AE7',
            'kategori' => 'ATK',
            'tipe_unit' => 'PCS',
            'harga' => 2500,
            'keterangan' => 'Tinta hitam',
            'stok' => 100,
        ];
    }

    public function collection(Collection $rows): void
    {
        DB::transaction(function () use ($rows): void {
            foreach ($rows as $row) {
                $name = $this->text($row['nama_barang'] ?? null);

                if ($name === null) {
                    continue;
                }

                $category = $this->text($row['kategori'] ?? null);
                $unit = $this->text($row['tipe_unit'] ?? null);

                Product::query()->updateOrCreate(['name' => $name], array_filter([
                    'item_code' => $this->text($row['kode_barang'] ?? null),
                    'product_category_id' => $category === null ? null : ProductCategory::query()->firstOrCreate(['name' => $category])->id,
                    'unit_type_id' => $unit === null ? null : UnitType::query()->firstOrCreate(['name' => $unit])->id,
                    'price' => $this->number($row['harga'] ?? null),
                    'description' => $this->text($row['keterangan'] ?? null),
                    'stock' => $this->number($row['stok'] ?? null),
                ], fn (mixed $value): bool => $value !== null));
            }
        });
    }

    public function chunkSize(): int
    {
        return 250;
    }
}
