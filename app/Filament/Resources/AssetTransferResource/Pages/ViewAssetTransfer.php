<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Filament\Resources\AssetTransferResource;
use App\Models\AssetTransfer;
use Carbon\Carbon;
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
        return parent::resolveRecord($key)->loadMissing(['details.asset', 'fromUser.jobTitle', 'toUser.jobTitle', 'businessEntity']);
    }

    public function getTitle(): string
    {
        return $this->getRecord()->letter_number ?: 'Detail Aset Transfer';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();

        return collect([$record->status, $record->transfer_date ? Carbon::parse($record->transfer_date)->translatedFormat('d M Y') : null])->filter()->implode(' · ');
    }

    // Ubah di paling kanan sebagai aksi utama, sama seperti halaman detail lain.
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clear_document')
                ->label('Kosongkan Dokumen')
                ->color('gray')
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
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (AssetTransfer $record): string => route('asset-transfer.download', $record))
                ->color('gray'),
            EditAction::make(),
        ];
    }
}
