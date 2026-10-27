<?php

namespace App\Filament\Resources\RentResource\Pages;

use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\RentResource;
use App\Imports\RentImport;
use App\Models\Rent;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRents extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = RentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelImportAction::make()
                ->label('Impor Excel')
                ->color('gray')
                ->use(RentImport::class)
                ->sampleExcel(sampleData: [RentImport::sampleRow()], fileName: 'template-sewa.xlsx')
                ->visible(fn (): bool => auth()->user()?->can('import', Rent::class) ?? false),
            CreateAction::make(),
        ];
    }
}
