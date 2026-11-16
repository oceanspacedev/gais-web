<?php

namespace App\Filament\Resources\SecurityChecksheetResource\Pages;

use App\Filament\Resources\SecurityChecksheetResource;
use App\Models\SecurityChecksheet;
use App\Support\SecurityChecklist;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

/**
 * @property SecurityChecksheet $record
 */
class EditSecurityChecksheet extends EditRecord
{
    protected static string $resource = SecurityChecksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Snapshot checklist dibuka menjadi isian per pertanyaan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['checklist'] = SecurityChecklist::answers($this->record->checklistEntries());

        return $data;
    }

    /**
     * Pertanyaan tetap yang tersimpan di laporan; hanya jawaban dan hasil cocoknya yang diperbarui.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return [...$data, ...SecurityChecklist::build(
            SecurityChecklist::recordDefinitions($this->record),
            (array) ($data['checklist'] ?? []),
        )];
    }
}
