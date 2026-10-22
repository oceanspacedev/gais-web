<?php

namespace App\Filament\Resources\ItemRequestResource\Pages;

use App\Filament\Resources\ItemRequestResource;
use App\Models\ItemRequest;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemRequest extends ViewRecord
{
    protected static string $resource = ItemRequestResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->request_code ?: 'Detail Pengajuan';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return ($record->type?->name ?? '-').' · '.$record->client_status->label();
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ItemRequestResource::workflowActions(),
            EditAction::make()->visible(fn (): bool => (auth()->user()?->can('update', $this->getRecord()) ?? false)
                && (auth()->user()?->can('changeApplicant', ItemRequest::class) ?? false)),
        ];
    }
}
