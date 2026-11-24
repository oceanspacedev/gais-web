<?php

namespace App\Filament\Resources\ItemRequestResource\Pages;

use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ItemRequestResource;
use App\Models\User;
use App\Services\ItemRequestWorkflow;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateItemRequest extends CreateRecord
{
    protected static string $resource = ItemRequestResource::class;

    public function mount(): void
    {
        $user = auth()->user();

        // Pemohon tanpa divisi (mis. role requester dari users:assign-default-role)
        // memilih divisinya dulu di halaman daftar.
        if ($user instanceof User && $user->needsToChooseDivision()) {
            Notification::make()->title('Pilih divisi Anda dulu')->warning()->send();

            $this->redirect(ItemRequestResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    /**
     * Pembuatan lewat ItemRequestWorkflow supaya kuota, syarat tipe, detail
     * barang, dan baris approval dibuat dengan aturan yang sama di mana pun.
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ItemRequestWorkflow::class)->create(auth()->user(), $data);
        } catch (ItemRequestException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
