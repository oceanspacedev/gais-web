<?php

namespace App\Filament\Resources\InsuranceResource\Pages;

use App\Filament\Resources\InsuranceResource;
use App\Imports\InsuranceImport;
use App\Models\Insurance;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInsurances extends ListRecords
{
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
            CreateAction::make(),
        ];
    }
}
