<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewObChecksheet extends ViewRecord
{
    protected static string $resource = ObChecksheetResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->reference_number ?: 'Detail OB Checksheet';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->room, $record->scheduled_date?->translatedFormat('d M Y')])->filter()->implode(' · ') ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
