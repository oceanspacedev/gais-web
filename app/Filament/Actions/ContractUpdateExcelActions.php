<?php

namespace App\Filament\Actions;

use App\Exports\InsuranceUpdateExport;
use App\Exports\RentUpdateExport;
use App\Imports\InsuranceUpdateImport;
use App\Imports\RentUpdateImport;
use Closure;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Header action tab Riwayat Pembaharuan/Perpanjangan: impor Excel bertemplate
 * sumo ke satu kontrak dan ekspor baris kontrak itu dengan kolom yang sama
 * (sumo: importUpdate, templateUpdate, exportUpdate). Hak akses diatur pemanggil.
 */
final class ContractUpdateExcelActions
{
    /**
     * @param  class-string<InsuranceUpdateImport|RentUpdateImport>  $importClass
     */
    public static function import(string $name, string $label, string $importClass, Model $owner, string $templateFileName, string $description): ExcelImportAction
    {
        return ExcelImportAction::make($name)
            ->label($label)
            ->color('gray')
            ->modalHeading($label)
            ->modalDescription($description)
            ->use($importClass, $owner)
            ->sampleExcel(sampleData: [$importClass::sampleRow($owner)], fileName: $templateFileName, sampleButtonLabel: 'Unduh template')
            ->uploadField(fn (FileUpload $upload): FileUpload => $upload->label('File Excel'));
    }

    /**
     * @param  Closure(): (InsuranceUpdateExport|RentUpdateExport)  $export
     */
    public static function export(string $name, string $label, Closure $export): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->action(function () use ($export): BinaryFileResponse {
                $file = $export();

                return Excel::download($file, $file->fileName());
            });
    }
}
