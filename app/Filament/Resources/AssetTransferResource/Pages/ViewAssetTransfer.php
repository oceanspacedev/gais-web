<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ViewAssetTransfer extends ViewRecord
{
    protected static string $resource = AssetTransferResource::class;

    // Daftar aset di infolist; tanpa ini setiap baris memuat asetnya sendiri.
    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->loadMissing('details.asset');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('clear_document')
                ->label('Kosongkan Dokumen')
                ->color('warning')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->modalHeading('Kosongkan Dokumen')
                ->modalDescription('Apakah Anda yakin ingin mengosongkan dokumen ini?')
                ->modalSubmitActionLabel('Ya, kosongkan')
                ->visible(fn (AssetTransfer $record): bool => $record->document !== null)
                ->action(function (AssetTransfer $record) {
                    if ($record->document) {
                        Storage::disk('public')->delete($record->document);
                        $record->update(['document' => null]);
                        Notification::make()
                            ->title('Dokumen berhasil dikosongkan')
                            ->success()
                            ->send();
                    }
                }),
            Action::make('download')
                ->label('Download PDF')
                ->url(fn (AssetTransfer $record): string => route('asset-transfer.download', $record))
                ->color('info'),
        ];
    }
}
