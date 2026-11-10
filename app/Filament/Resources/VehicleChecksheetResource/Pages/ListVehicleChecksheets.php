<?php

namespace App\Filament\Resources\VehicleChecksheetResource\Pages;

use App\Filament\Actions\ExcelActionGroup;
use App\Filament\Resources\VehicleChecksheetResource;
use App\Imports\VehicleChecksheetImport;
use App\Support\StoredFile;
use Asmit\ResizedColumn\HasResizableColumn;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListVehicleChecksheets extends ListRecords
{
    use HasResizableColumn;

    protected static string $resource = VehicleChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelActionGroup::make([
                ExportAction::make()
                    ->label('Ekspor Excel')
                    ->icon(ExcelActionGroup::EXPORT_ICON)
                    ->color('gray')
                    ->visible(fn () => auth()->user()->can('export', static::$resource::getModel()))
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withColumns([
                                Column::make('reference_number')->heading('Nomor Referensi'),
                                Column::make('license_plate')->heading('Plat Nomor'),
                                Column::make('pic')->heading('PIC'),
                                Column::make('location')->heading('Lokasi'),
                                Column::make('destination')->heading('Tujuan'),
                                Column::make('start_km')->heading('Kilometer Awal'),
                                Column::make('departure_time')->heading('Waktu Keberangkatan'),
                                Column::make('departure_photo')
                                    ->heading('Foto Keberangkatan')
                                    ->getStateUsing(fn ($record) => $record->departure_photo ? StoredFile::downloadUrl($record->departure_photo) : null),
                                Column::make('departure_damage_report')
                                    ->heading('Laporan Kerusakan Keberangkatan')
                                    ->getStateUsing(fn ($record) => $record->departure_damage_report ? StoredFile::downloadUrl($record->departure_damage_report) : null),
                                Column::make('end_km')->heading('Kilometer Akhir'),
                                Column::make('return_time')->heading('Waktu Pengembalian'),
                                Column::make('return_photo')
                                    ->heading('Foto Pengembalian')
                                    ->getStateUsing(fn ($record) => $record->return_photo ? StoredFile::downloadUrl($record->return_photo) : null),
                                Column::make('return_damage_report')
                                    ->heading('Laporan Kerusakan Pengembalian')
                                    ->getStateUsing(fn ($record) => $record->return_damage_report ? StoredFile::downloadUrl($record->return_damage_report) : null),
                                Column::make('rental_duration')->heading('Durasi Sewa'),
                                Column::make('distance_traveled')->heading('Jarak Tempuh'),
                                Column::make('remarks')->heading('Catatan Tambahan'),
                                Column::make('created_at')->heading('Tanggal Dibuat'),
                                Column::make('updated_at')->heading('Tanggal Diperbarui'),
                            ])
                            ->withFilename('export_vehicle_checksheet_'.date('Y-m-d')),
                    ]),
                ExcelImportAction::make()
                    ->label('Impor Excel')
                    ->icon(ExcelActionGroup::IMPORT_ICON)
                    ->color('gray')
                    ->use(VehicleChecksheetImport::class)
                    ->visible(fn () => auth()->user()->can('import', static::$resource::getModel())),
            ]),
            Actions\CreateAction::make(),
        ];
    }
}
