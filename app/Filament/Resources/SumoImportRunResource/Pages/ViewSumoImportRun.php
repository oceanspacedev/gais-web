<?php

namespace App\Filament\Resources\SumoImportRunResource\Pages;

use App\Filament\Resources\SumoImportRunResource;
use App\Jobs\Sumo\LoadSumoDump;
use App\Jobs\Sumo\RunSumoImport;
use App\Models\SumoImportRun;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * @property SumoImportRun $record
 */
class ViewSumoImportRun extends ViewRecord
{
    protected static string $resource = SumoImportRunResource::class;

    /** Run dianggap macet bila status sibuknya tidak berubah lebih lama dari timeout job. */
    private const STUCK_AFTER_MINUTES = 65;

    public function getTitle(): string
    {
        return "Impor sumo #{$this->record->id}";
    }

    public function getSubheading(): ?string
    {
        return match ($this->record->status) {
            SumoImportRun::STATUS_QUEUED => 'Menunggu worker antrean (php artisan horizon).',
            SumoImportRun::STATUS_LOADING, SumoImportRun::STATUS_DRY_RUNNING, SumoImportRun::STATUS_IMPORTING => 'Sedang berjalan; log di bawah diperbarui otomatis.',
            SumoImportRun::STATUS_READY => 'Uji coba selesai. Periksa pemetaan dan peringatan, lalu jalankan Impor Sungguhan bila sudah benar.',
            SumoImportRun::STATUS_IMPORTED => 'Data sudah diimpor ke shelf. Impor ulang hanya menambah baris yang belum ada.',
            SumoImportRun::STATUS_FAILED => 'Gagal. Lihat penyebab dan log, perbaiki, lalu ulangi.',
            default => null,
        };
    }

    /**
     * Dipanggil wire:poll dari tampilan log selama run sibuk.
     */
    public function refreshRun(): void
    {
        $this->record->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Impor sungguhan')
                ->icon('heroicon-o-arrow-down-on-square-stack')
                ->color('danger')
                ->visible(fn (): bool => $this->record->canImport())
                ->modalHeading('Impor data sumo ke shelf?')
                ->modalDescription(fn (): string => $this->importSummary())
                ->schema([
                    TextInput::make('confirmation')
                        ->label('Ketik IMPOR untuk melanjutkan')
                        ->required()
                        ->in(['IMPOR'])
                        ->validationMessages(['in' => 'Ketik IMPOR (huruf besar).']),
                ])
                ->modalSubmitActionLabel('Impor sekarang')
                ->action(fn () => $this->queue(fn () => RunSumoImport::dispatchFor($this->record, dryRun: false, userId: auth()->id()), 'Impor sungguhan masuk antrean.')),
            Action::make('dryRun')
                ->label('Uji coba ulang')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->visible(fn (): bool => $this->record->canDryRun())
                ->requiresConfirmation()
                ->modalDescription('Menjalankan importer terhadap dump di staging lalu membatalkan semua perubahan. Berguna setelah config/sumo-import.php diubah.')
                ->action(fn () => $this->queue(fn () => RunSumoImport::dispatchFor($this->record, dryRun: true), 'Uji coba masuk antrean.')),
            Action::make('reload')
                ->label('Muat ulang ke staging')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (): bool => $this->record->canReload())
                ->requiresConfirmation()
                ->modalDescription(fn (): string => 'Isi database staging `'.SumoImportRunResource::stagingDatabase().'` diganti dengan dump ini, lalu uji coba dijalankan.')
                ->action(fn () => $this->queue(fn () => LoadSumoDump::dispatchWithDryRun($this->record), 'Muat ulang dan uji coba masuk antrean.')),
            Action::make('cancel')
                ->label('Batalkan')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === SumoImportRun::STATUS_QUEUED
                    || ($this->record->isBusy() && $this->record->updated_at->lt(now()->subMinutes(self::STUCK_AFTER_MINUTES))))
                ->requiresConfirmation()
                ->modalDescription('Run ditandai dibatalkan; job yang belum jalan akan dilewati. Pakai hanya bila worker memang tidak memprosesnya (cek Horizon).')
                ->action(function (): void {
                    $this->record->appendLog('Dibatalkan oleh '.auth()->user()?->name.'.');
                    $this->record->forceFill(['status' => SumoImportRun::STATUS_CANCELLED, 'staged' => false])->save();
                    $this->record->flushLog();
                }),
            Action::make('download')
                ->label('Unduh dump')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn (): StreamedResponse => Storage::disk($this->record->file_disk)->download($this->record->file_path, $this->record->original_name)),
        ];
    }

    /**
     * @param  callable(): void  $dispatch
     */
    private function queue(callable $dispatch, string $message): void
    {
        try {
            $dispatch();

            Notification::make()->title($message)->success()->send();
        } catch (Throwable $exception) {
            // Antrean sync: job sudah menandai run gagal; tampilkan penyebabnya.
            report($exception);

            Notification::make()->title('Gagal')->body($exception->getMessage())->danger()->persistent()->send();
        }

        $this->record->refresh();
    }

    private function importSummary(): string
    {
        return sprintf(
            'Memakai dump yang sama dengan uji coba %s (%d peringatan). Impor berjalan dalam satu transaksi: bila gagal, tidak ada yang tersimpan. Pastikan database shelf sudah di-backup.',
            $this->record->dry_run_at?->format('d M Y H:i') ?? '-',
            count($this->record->dry_run_report['warnings'] ?? []),
        );
    }
}
