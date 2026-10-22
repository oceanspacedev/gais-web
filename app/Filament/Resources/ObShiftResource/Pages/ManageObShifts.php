<?php

namespace App\Filament\Resources\ObShiftResource\Pages;

use App\Filament\Resources\ObShiftResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageObShifts extends ManageRecords
{
    protected static string $resource = ObShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('lg'),
        ];
    }
}
