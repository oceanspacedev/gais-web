<?php

namespace App\Filament\Concerns;

use App\Models\Division;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Halaman daftar yang meminta karyawan tanpa divisi (User::needsToChooseDivision())
 * memilih divisi/toko tempatnya bekerja, sekali saja (User::chooseOwnDivision()).
 * Panggil promptForDivision() dari mount() supaya modalnya langsung terbuka, dan
 * sembunyikan tombol buat selama needsDivision() masih true.
 */
trait ChoosesOwnDivision
{
    /**
     * Yang bisa dilakukan setelah divisi terisi, mis. "membuat pengajuan barang".
     */
    abstract protected function divisionUnlocks(): string;

    protected function promptForDivision(): void
    {
        if ($this->needsDivision()) {
            $this->defaultAction = 'chooseDivision';
        }
    }

    protected function chooseDivisionAction(): Action
    {
        return Action::make('chooseDivision')
            ->label('Pilih Divisi')
            ->icon('heroicon-o-building-storefront')
            ->visible(fn (): bool => $this->needsDivision())
            ->modalHeading('Pilih divisi Anda')
            ->modalDescription(fn (): string => "Data divisi Anda belum ada. Pilih divisi/toko tempat Anda bekerja agar bisa {$this->divisionUnlocks()}. Pengajuan dan laporan Anda akan terlihat oleh rekan satu divisi. Pilihan ini hanya bisa dilakukan sekali; bila keliru, hubungi admin GA untuk mengubahnya.")
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Simpan')
            ->schema([
                Select::make('division_id')
                    ->label('Divisi / toko')
                    ->placeholder('Ketik nama divisi, mis. CS-TEGAL')
                    ->options(fn (): array => Division::query()
                        ->with('area')
                        ->where('name', '!=', '-') // divisi placeholder dari sumo
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Division $division): array => [
                            $division->id => $division->area ? "{$division->name} ({$division->area->name})" : $division->name,
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var User $user */
                $user = auth()->user();
                $division = Division::query()->findOrFail($data['division_id']);

                if (! $user->chooseOwnDivision($division)) {
                    Notification::make()->title('Divisi Anda sudah terisi')->body('Hubungi admin GA bila perlu mengubahnya.')->warning()->send();

                    return;
                }

                Log::info('User memilih divisinya sendiri', ['user_id' => $user->id, 'division_id' => $division->id, 'division' => $division->name]);

                Notification::make()->title("Divisi disimpan: {$division->name}")->body("Sekarang Anda bisa {$this->divisionUnlocks()}.")->success()->send();

                $this->redirect(static::getResource()::getUrl('index'));
            });
    }

    protected function needsDivision(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->needsToChooseDivision();
    }
}
