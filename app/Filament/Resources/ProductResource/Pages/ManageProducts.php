<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Exports\ProductExport;
use App\Filament\Actions\DownloadExcelAction;
use App\Filament\Resources\ProductResource;
use App\Imports\ProductImport;
use App\Models\Product;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProducts extends ManageRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Impor Excel')
                ->color('gray')
                ->use(ProductImport::class)
                ->sampleExcel(sampleData: [ProductImport::sampleRow()], fileName: 'template-barang.xlsx')
                ->visible(fn (): bool => auth()->user()?->can('import', Product::class) ?? false),
            // Sumo: /product/export, kolom sama dengan template impor.
            DownloadExcelAction::make('exportAll', 'Ekspor Semua Barang', fn (): ProductExport => new ProductExport)
                ->visible(fn (): bool => auth()->user()?->can('export', Product::class) ?? false),
            CreateAction::make()->slideOver()->modalWidth('lg'),
        ];
    }
}
