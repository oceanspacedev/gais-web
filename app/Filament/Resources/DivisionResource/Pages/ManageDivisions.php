<?php

namespace App\Filament\Resources\DivisionResource\Pages;

use App\Filament\Concerns\MemoizesTableQuery;
use App\Filament\Resources\DivisionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDivisions extends ManageRecords
{
    use MemoizesTableQuery;

    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
