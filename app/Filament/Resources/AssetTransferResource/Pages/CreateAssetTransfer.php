<?php

namespace App\Filament\Resources\AssetTransferResource\Pages;

use App\Enums\AssetTransferDocumentType;
use App\Exceptions\AssetTransferException;
use App\Filament\Resources\AssetTransferResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class CreateAssetTransfer extends CreateRecord
{
    protected static string $resource = AssetTransferResource::class;

    /**
     * BA, detail aset, dan mutasi aset disimpan dalam satu transaksi: bila
     * aturan siklus hidup menolak (pihak bukan staf GA, aset tidak di stok,
     * dan sebagainya) tidak ada BA yatim yang tertinggal.
     */
    protected ?bool $hasDatabaseTransactions = true;

    /**
     * Pihak GA pada BA adalah akun yang sedang login. Field-nya terkunci di
     * form, tetapi nilainya ditetapkan di sini supaya tidak bergantung pada
     * apa yang dikirim browser. Pemegang izin "Kelola BA Stok" boleh memilih
     * staf GA lain.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Auth::user();

        if (! $actor instanceof User || $actor->canManageStockTransfers() || ! $actor->isGeneralAffair()) {
            return $data;
        }

        $type = $data['document_type'] ?? null;
        $type = $type instanceof AssetTransferDocumentType ? $type : AssetTransferDocumentType::tryFrom((string) $type);

        if ($type?->requiresGeneralAffairFrom()) {
            $data['from_user_id'] = $actor->getKey();
        }

        if ($type?->requiresGeneralAffairTo()) {
            $data['to_user_id'] = $actor->getKey();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $actor = auth()->user();

        try {
            $this->record->applyLifecycleToAssets($actor instanceof User ? $actor : null);
        } catch (AssetTransferException|AuthorizationException $exception) {
            // BA ini di-rollback. Lepaskan record-nya supaya Livewire tidak
            // memuat ulang baris yang sudah tidak ada (404) saat form dikirim lagi.
            $this->record = null;

            Notification::make()
                ->title('BA tidak dapat dibuat')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction();
        }
    }
}
