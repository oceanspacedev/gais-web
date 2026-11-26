<?php

namespace App\Filament\Resources\ProblemReportResource\Pages;

use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ProblemReportResource;
use App\Models\User;
use App\Services\ProblemReportWorkflow;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateProblemReport extends CreateRecord
{
    protected static string $resource = ProblemReportResource::class;

    public function mount(): void
    {
        $user = auth()->user();

        // Pelapor tanpa divisi memilih divisinya dulu di halaman daftar, supaya
        // laporannya tercatat ke divisi dan terlihat oleh rekan sedivisi.
        if ($user instanceof User && $user->needsToChooseDivision()) {
            Notification::make()->title('Pilih divisi Anda dulu')->warning()->send();

            $this->redirect(ProblemReportResource::getUrl('index'));

            return;
        }

        parent::mount();
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ProblemReportWorkflow::class)->create(auth()->user(), $data);
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
