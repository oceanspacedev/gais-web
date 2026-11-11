<?php

namespace App\Filament\Resources\SecurityChecksheetResource\Pages;

use App\Filament\Resources\SecurityChecksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSecurityChecksheet extends ViewRecord
{
    protected static string $resource = SecurityChecksheetResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->reference_number ?: 'Detail Security Checksheet';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->location, $record->report_date?->translatedFormat('d M Y')])->filter()->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
