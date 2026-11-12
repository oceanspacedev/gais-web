<?php

namespace App\Filament\Resources\SecurityChecklistItemResource\Pages;

use App\Filament\Resources\SecurityChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSecurityChecklistItems extends ManageRecords
{
    protected static string $resource = SecurityChecklistItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SecurityChecklistItemResource::previewAction(),
            Actions\CreateAction::make()
                ->label('Tambah')
                ->slideOver()
                ->modalWidth('lg')
                ->mutateDataUsing(fn (array $data): array => SecurityChecklistItemResource::fromFormData($data)),
        ];
    }
}
