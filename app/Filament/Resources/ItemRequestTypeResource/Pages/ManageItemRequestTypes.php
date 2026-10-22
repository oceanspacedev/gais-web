<?php

namespace App\Filament\Resources\ItemRequestTypeResource\Pages;

use App\Filament\Resources\ItemRequestTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItemRequestTypes extends ManageRecords
{
    protected static string $resource = ItemRequestTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver()->modalWidth('md'),
        ];
    }
}
