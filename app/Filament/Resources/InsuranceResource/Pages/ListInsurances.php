<?php

namespace App\Filament\Resources\InsuranceResource\Pages;

use App\Exports\InsuranceExport;
use App\Filament\Actions\DownloadExcelAction;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\InsuranceResource;
use App\Imports\InsuranceImport;
use App\Models\Insurance;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInsurances extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = InsuranceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Impor Excel')
                ->color('gray')
                ->use(InsuranceImport::class)
                ->sampleExcel(sampleData: [InsuranceImport::sampleRow()], fileName: 'template-asuransi.xlsx')
                ->visible(fn (): bool => auth()->user()?->can('import', Insurance::class) ?? false),
            // Sumo: /insurance/export, kolom template impor termasuk id.
            DownloadExcelAction::make('exportAll', 'Ekspor Semua Asuransi', fn (): InsuranceExport => new InsuranceExport)
                ->visible(fn (): bool => auth()->user()?->can('export', Insurance::class) ?? false),
            CreateAction::make(),
        ];
    }
}
