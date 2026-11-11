<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name ?: 'Detail Task';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->code, TaskResource::statusLabel($record->status)])->filter()->implode(' · ') ?: null;
    }

    // Ubah di paling kanan sebagai aksi utama, sama seperti halaman detail lain.
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn ($record) => $record->status === 'completed') // Only show if task is completed
                ->url(fn ($record) => route('task-completion.download', $record->id)) // Generate URL for download
                ->openUrlInNewTab(), // Open the download in a new tab

            Actions\EditAction::make(),
        ];
    }
}
