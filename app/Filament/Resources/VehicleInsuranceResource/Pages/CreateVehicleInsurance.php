<?php

namespace App\Filament\Resources\VehicleInsuranceResource\Pages;

use App\Filament\Resources\VehicleInsuranceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleInsurance extends CreateRecord
{
    protected static string $resource = VehicleInsuranceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
