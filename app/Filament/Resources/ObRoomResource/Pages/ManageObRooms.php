<?php

namespace App\Filament\Resources\ObRoomResource\Pages;

use App\Filament\Resources\ObRoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageObRooms extends ManageRecords
{
    protected static string $resource = ObRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->slideOver()
                ->modalWidth('md'),
        ];
    }
}
