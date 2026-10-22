<?php

namespace App\Filament\Resources\InsuranceScopeResource\Pages;

use App\Filament\Resources\InsuranceScopeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsuranceScopes extends ManageRecords
{
    protected static string $resource = InsuranceScopeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
