<?php

namespace App\Filament\Resources\SecurityChecksheetResource\Pages;

use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Exports\SecurityChecksheetExporter;
use App\Filament\Resources\SecurityChecksheetResource;
use App\Models\SecurityChecksheet;
use Filament\Actions;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListSecurityChecksheets extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = SecurityChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(SecurityChecksheetExporter::class)
                ->label('Ekspor Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->can('export', SecurityChecksheet::class) ?? false),
            Actions\CreateAction::make(),
        ];
    }
}
