<?php

namespace App\Filament\Resources\InsuranceCategoryResource\Pages;

use App\Filament\Resources\InsuranceCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInsuranceCategories extends ManageRecords
{
    protected static string $resource = InsuranceCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
