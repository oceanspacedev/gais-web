<?php

namespace App\Filament\Resources\VehicleInsuranceResource\Pages;

use App\Filament\Resources\VehicleInsuranceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVehicleInsurance extends ViewRecord
{
    protected static string $resource = VehicleInsuranceResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->policy_number ?: 'Detail Asuransi Kendaraan';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->licensePlate(), $record->insured_name, $record->effectiveStatus()?->label()])->filter()->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
