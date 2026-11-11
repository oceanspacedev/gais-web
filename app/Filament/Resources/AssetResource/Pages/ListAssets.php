<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Actions\ExcelActionGroup;
use App\Filament\Actions\ExportCsaAuditFormatAction;
use App\Filament\Actions\ExportVehicleAuditFormatAction;
use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\AssetReconciliationResource;
use App\Filament\Resources\AssetResource;
use App\Imports\AssetImport;
use App\Models\Asset;
use Asmit\ResizedColumn\HasResizableColumn;
use EightyNine\ExcelImport\ExcelImportAction;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    use HasResizableColumn, MemoizesTableQuery;

    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExcelActionGroup::make([
                ExportCsaAuditFormatAction::make(),
                ExportVehicleAuditFormatAction::make(),
                Actions\Action::make('auditReconciliation')
                    ->label('Impor & Laporan Audit')
                    ->icon('heroicon-o-arrows-right-left')
                    ->color('gray')
                    ->tooltip('Sinkronisasi eksternal: Export format → isi audit → Import → Laporan → Apply (CSA atau Kendaraan).')
                    ->url(AssetReconciliationResource::getUrl('index'))
                    ->visible(fn () => auth()->user()->can('import', static::$resource::getModel())),
                ExportAction::make()
                    ->label('Ekspor Excel')
                    ->icon(ExcelActionGroup::EXPORT_ICON)
                    ->color('gray')
                    ->exporter(AssetExporter::class)
                    ->chunkSize(250)
                    ->columnMappingColumns(2)
                    ->visible(fn () => auth()->user()?->can('export', Asset::class) ?? false),
                ExcelImportAction::make()
                    ->label('Impor Excel')
                    ->icon(ExcelActionGroup::IMPORT_ICON)
                    ->color('gray')
                    ->use(AssetImport::class)
                    ->visible(fn () => auth()->user()->can('import', static::$resource::getModel())),
            ]),
            Actions\CreateAction::make(),
        ];
    }
}
