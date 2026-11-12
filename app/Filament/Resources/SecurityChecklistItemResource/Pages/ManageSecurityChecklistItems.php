<?php

namespace App\Filament\Resources\SecurityChecklistItemResource\Pages;

use App\Filament\Resources\SecurityChecklistItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSecurityChecklistItems extends ManageRecords
{
    protected static string $resource = SecurityChecklistItemResource::class;

    public function getSubheading(): ?string
    {
        return 'Pertanyaan yang dijawab security di Security Checksheet. Seret baris untuk mengubah urutan.';
    }

    protected function getHeaderActions(): array
    {
        return [
            SecurityChecklistItemResource::previewAction(),
            Actions\CreateAction::make()
                ->label('Tambah Pertanyaan')
                ->slideOver()
                ->modalWidth('lg')
                ->mutateDataUsing(fn (array $data): array => SecurityChecklistItemResource::fromFormData($data)),
        ];
    }
}
