<?php

namespace App\Filament\Resources\SecurityChecksheetResource\Pages;

use App\Filament\Resources\SecurityChecksheetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSecurityChecksheet extends EditRecord
{
    protected static string $resource = SecurityChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
