<?php

namespace App\Filament\Resources\ObChecksheetResource\Pages;

use App\Filament\Resources\ObChecksheetResource;
use App\Models\ObChecksheet;
use App\Support\ObPhotoDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateObChecksheet extends CreateRecord
{
    protected static string $resource = ObChecksheetResource::class;

    protected int $createdRoomCount = 0;

    protected bool $draftCleared = false;

    /**
     * Pulihkan pekerjaan dan foto yang sudah diisi bila halaman termuat ulang
     * sebelum disimpan (lihat ObPhotoDraft).
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $draft = ObPhotoDraft::get(ObPhotoDraft::CREATE);

        if ($draft === []) {
            return;
        }

        $this->form->fill(['rooms' => array_map(fn (array $room): array => [
            'room' => $room['room'] ?? null,
            'before_photo' => $room['before_photo'] ?? null,
        ], array_values($draft))]);

        Notification::make()
            ->title('Isian sebelumnya dipulihkan')
            ->body('Pekerjaan dan foto yang belum disimpan dikembalikan. Tekan Mulai untuk menyimpannya.')
            ->info()
            ->send();
    }

    /**
     * Livewire memanggil ini di akhir setiap request: simpan isian terbaru sebagai draf.
     */
    public function dehydrate(): void
    {
        if ($this->draftCleared) {
            return;
        }

        $draft = [];

        foreach ($this->data['rooms'] ?? [] as $room) {
            $name = is_array($room) ? ($room['room'] ?? null) : null;
            $photo = ObChecksheetResource::photoPath(is_array($room) ? ($room['before_photo'] ?? null) : null);

            if (blank($name) && $photo === '') {
                continue;
            }

            $draft[] = ['room' => $name, 'before_photo' => $photo ?: null];
        }

        ObPhotoDraft::put(ObPhotoDraft::CREATE, $draft);
    }

    // Satu tombol saja: daftar pekerjaan sudah bisa ditambah di dalam form.
    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Mulai Pekerjaan';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Mulai');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        $count = $this->createdRoomCount;

        return $count === 1
            ? 'Pekerjaan dimulai. Ambil Foto Sesudah setelah selesai.'
            : "{$count} pekerjaan dimulai. Ambil Foto Sesudah satu per satu setelah selesai.";
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $rooms = array_values($data['rooms'] ?? []);

        if ($rooms === []) {
            throw ValidationException::withMessages([
                'data.rooms' => 'Tambahkan minimal satu pekerjaan.',
            ]);
        }

        $created = null;

        foreach ($rooms as $room) {
            $photo = $room['before_photo'] ?? null;

            if (is_array($photo)) {
                $photo = collect($photo)->first(fn (mixed $value): bool => is_string($value) && $value !== '');
            }

            $created = ObChecksheet::create([
                'user_id' => auth()->id(),
                'ob_shift_id' => filled($data['ob_shift_id'] ?? null) ? (int) $data['ob_shift_id'] : null,
                'room' => $room['room'] ?? null,
                'before_photo' => is_string($photo) ? $photo : null,
                'notes' => filled($room['notes'] ?? null) ? $room['notes'] : null,
            ]);
        }

        $this->createdRoomCount = count($rooms);

        ObPhotoDraft::forget(ObPhotoDraft::CREATE);
        $this->draftCleared = true;

        return $created;
    }
}
