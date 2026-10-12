<?php

namespace App\Filament\Resources\ProblemReportCategoryResource\Pages;

use App\Filament\Resources\ProblemReportCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProblemReportCategories extends ManageRecords
{
    protected static string $resource = ProblemReportCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
