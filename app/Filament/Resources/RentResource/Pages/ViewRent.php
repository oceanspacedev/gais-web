<?php

namespace App\Filament\Resources\RentResource\Pages;

use App\Filament\Resources\RentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRent extends ViewRecord
{
    protected static string $resource = RentResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->rent_code ?: 'Detail Perjanjian Sewa';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return ($record->rented_detail ?: '-').' · '.$record->status->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
