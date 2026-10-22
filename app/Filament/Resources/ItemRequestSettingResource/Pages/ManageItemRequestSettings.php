<?php

namespace App\Filament\Resources\ItemRequestSettingResource\Pages;

use App\Filament\Resources\ItemRequestSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageItemRequestSettings extends ManageRecords
{
    protected static string $resource = ItemRequestSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->slideOver()->modalWidth('md'),
        ];
    }
}
