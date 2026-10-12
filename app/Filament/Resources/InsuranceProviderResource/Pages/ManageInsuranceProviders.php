<?php

namespace App\Filament\Resources\InsuranceProviderResource\Pages;

use App\Filament\Resources\InsuranceProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsuranceProviders extends ManageRecords
{
    protected static string $resource = InsuranceProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
