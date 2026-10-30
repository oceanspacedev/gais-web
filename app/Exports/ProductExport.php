<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Imports\ProductImport;
use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor semua barang dengan kolom template `ProductImport` (sumo: /product/export),
 * jadi hasilnya bisa diubah lalu diimpor kembali.
 */
class ProductExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function fileName(): string
    {
        return 'barang.xlsx';
    }

    /**
     * @return Collection<int, Product>
     */
    public function collection(): Collection
    {
        return Product::query()->with(['category', 'unitType'])->orderBy('name')->orderBy('id')->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys(ProductImport::sampleRow());
    }

    /**
     * @param  Product  $product
     * @return list<mixed>
     */
    public function map($product): array
    {
        return $this->orderedByHeadings([
            'nama_barang' => $product->name,
            'kategori' => $product->category?->name,
            'tipe_unit' => $product->unitType?->name,
            'harga' => $product->price === null ? null : (float) $product->price,
            'keterangan' => $product->description,
            'stok' => $product->stock === null ? null : (float) $product->stock,
        ]);
    }
}
