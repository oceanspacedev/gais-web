<?php

namespace App\Filament\Resources\SumoImportRunResource\Pages;

use App\Filament\Resources\SumoImportRunResource;
use App\Models\SumoImportRun;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSumoImportRuns extends ListRecords
{
    protected static string $resource = SumoImportRunResource::class;

    public function getSubheading(): ?string
    {
        return 'Alur: 1 Unggah dump → 2 Dimuat ke staging + uji coba otomatis → 3 Periksa laporan → 4 Impor sungguhan. Impor bisa diulang; baris yang sudah ada tidak ditimpa.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Unggah dump')
                ->icon('heroicon-o-arrow-up-tray')
                ->disabled(fn (): bool => SumoImportRun::anyBusy())
                ->tooltip(fn (): ?string => SumoImportRun::anyBusy() ? 'Masih ada impor yang antre atau berjalan.' : null),
        ];
    }
}
