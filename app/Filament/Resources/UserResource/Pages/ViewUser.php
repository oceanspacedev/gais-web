<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name ?: 'Detail Pengguna';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->jobTitle?->title, $record->businessEntity?->name])->filter()->implode(' · ') ?: null;
    }

    protected function getActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
