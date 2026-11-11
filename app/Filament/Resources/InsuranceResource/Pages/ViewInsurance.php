<?php

namespace App\Filament\Resources\InsuranceResource\Pages;

use App\Filament\Resources\InsuranceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInsurance extends ViewRecord
{
    protected static string $resource = InsuranceResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->policy_number ?: 'Detail Asuransi';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->insured_name, $record->effectiveStatus()?->label()])->filter()->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
