<?php

namespace App\Filament\Resources\SumoImportRunResource\Pages;

use App\Filament\Resources\SumoImportRunResource;
use App\Jobs\Sumo\LoadSumoDump;
use App\Models\SumoImportRun;
use App\Support\StoredFile;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CreateSumoImportRun extends CreateRecord
{
    protected static string $resource = SumoImportRunResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Unggah dump sumo';
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = (string) config('sumo-import.panel.disk');
        $path = is_array($data['file_path']) ? reset($data['file_path']) : $data['file_path'];
        $name = $data['original_name'] ?? basename($path);
        $name = is_array($name) ? reset($name) : $name;

        $problem = SumoImportRun::anyBusy() ? 'Masih ada impor yang antre atau berjalan. Tunggu sampai selesai.' : null;
        $problem ??= preg_match('/\.(sql|gz)$/i', $name) ? null : 'File harus berakhiran .sql atau .gz.';

        [$size, $checksum, $contentProblem] = StoredFile::withLocalPath($disk, $path, fn (string $local): array => [
            (int) filesize($local),
            hash_file('sha256', $local),
            $this->contentProblem($local, str_ends_with(strtolower($name), '.gz')),
        ]);
        $problem ??= $contentProblem;

        if ($problem !== null) {
            Storage::disk($disk)->delete($path);
            Notification::make()->title('Dump tidak bisa dipakai')->body($problem)->danger()->persistent()->send();

            $this->halt();
        }

        return [
            'user_id' => auth()->id(),
            'original_name' => $name,
            'file_disk' => $disk,
            'file_path' => $path,
            'file_size' => $size,
            'file_sha256' => $checksum,
            'notes' => $data['notes'] ?? null,
            'status' => SumoImportRun::STATUS_QUEUED,
        ];
    }

    protected function afterCreate(): void
    {
        try {
            LoadSumoDump::dispatchWithDryRun($this->record);
        } catch (Throwable $exception) {
            // Antrean sync (tes, tanpa worker): job gagal langsung, run sudah ditandai gagal.
            report($exception);
        }

        Notification::make()
            ->title('Dump diunggah')
            ->body('Dump dimuat ke staging lalu diuji coba di antrean. Halaman ini memperbarui log dengan sendirinya.')
            ->success()
            ->send();
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return SumoImportRunResource::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Pemeriksaan cepat isi file supaya file yang salah tidak sampai ke antrean.
     */
    private function contentProblem(string $path, bool $gzip): ?string
    {
        $handle = fopen($path, 'rb');
        $magic = (string) fread($handle, 2);
        fclose($handle);

        if ($gzip !== ($magic === "\x1f\x8b")) {
            return $gzip ? 'File .gz ini bukan arsip gzip.' : 'File ini terkompresi gzip; ganti akhiran namanya menjadi .gz.';
        }

        $gz = gzopen($path, 'rb');
        $head = (string) gzread($gz, 65536);
        gzclose($gz);

        if (str_contains($head, "\0")) {
            return 'Isi file bukan teks SQL.';
        }

        if (! preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE)\b/i', $head)) {
            return 'Tidak ditemukan CREATE TABLE / INSERT INTO di awal file. Pastikan ini hasil mysqldump.';
        }

        return null;
    }
}
