<?php

namespace App\Filament\Resources\SecurityChecksheetResource\Pages;

use App\Filament\Resources\SecurityChecksheetResource;
use App\Support\ObPhotoDraft;
use App\Support\SecurityChecklist;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSecurityChecksheet extends CreateRecord
{
    protected static string $resource = SecurityChecksheetResource::class;

    protected static bool $canCreateAnother = false;

    /** Isian teks yang ikut disimpan sebagai draf; lampiran tidak (masih file sementara). */
    private const DRAFT_FIELDS = ['location', 'checklist', 'findings'];

    protected bool $draftCleared = false;

    /**
     * Di HP halaman sering termuat ulang saat kamera dibuka untuk lampiran;
     * isian checklist yang belum disimpan dipulihkan dari draf (lihat ObPhotoDraft).
     */
    protected function fillForm(): void
    {
        parent::fillForm();

        $draft = ObPhotoDraft::get(ObPhotoDraft::SECURITY);

        if ($draft === []) {
            return;
        }

        $this->form->fill([...$this->data ?? [], ...$draft]);

        Notification::make()
            ->title('Isian sebelumnya dipulihkan')
            ->body('Checklist yang belum disimpan dikembalikan. Lampiran perlu dipilih ulang.')
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

        $draft = array_intersect_key($this->data ?? [], array_flip(self::DRAFT_FIELDS));

        // Draf baru berarti bila checklist atau temuan sudah mulai diisi; lokasi saja hanya isian awal.
        $started = filled($draft['findings'] ?? null)
            || collect($draft['checklist'] ?? [])->flatten()->filter(fn (mixed $value): bool => filled($value))->isNotEmpty();

        ObPhotoDraft::put(ObPhotoDraft::SECURITY, $started ? $draft : []);
    }

    public function getTitle(): string
    {
        return 'Buat Laporan Security';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Kirim Laporan');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Laporan security terkirim.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Laporan baru selalu atas nama pembuatnya, apa pun isi request.
        $data['user_id'] = auth()->id();

        // Jawaban disimpan bersama salinan pertanyaan aktif dan hasil cocok kuncinya.
        $data = [...$data, ...SecurityChecklist::build(SecurityChecklist::activeDefinitions(), (array) ($data['checklist'] ?? []))];

        $record = parent::handleRecordCreation($data);

        ObPhotoDraft::forget(ObPhotoDraft::SECURITY);
        $this->draftCleared = true;

        return $record;
    }
}
