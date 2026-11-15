<?php

namespace App\Filament\Resources\VehicleInsuranceResource\Pages;

use App\Filament\Resources\VehicleInsuranceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleInsurance extends EditRecord
{
    protected static string $resource = VehicleInsuranceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
