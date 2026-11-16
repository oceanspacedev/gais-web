<?php

namespace App\Filament\Resources\VehicleInsuranceResource\Pages;

use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\VehicleInsuranceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleInsurances extends ListRecords
{
    use MemoizesTableQuery;

    protected static string $resource = VehicleInsuranceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
