<?php

namespace App\Jobs\Sumo;

use App\Models\SumoImportRun;
use App\Services\Sumo\SumoDumpLoader;
use App\Support\StoredFile;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Memuat file dump sebuah run ke staging sumo. Dijalankan berantai
 * dengan uji coba supaya laporan langsung tersedia setelah unggah.
 */
class LoadSumoDump implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId)
    {
        $this->onConnection(config('sumo-import.panel.queue_connection'));
        $this->onQueue(config('sumo-import.panel.queue'));
    }

    /**
     * Antrekan muat ulang + uji coba untuk run ini.
     */
    public static function dispatchWithDryRun(SumoImportRun $run): void
    {
        $run->forceFill(['status' => SumoImportRun::STATUS_QUEUED, 'error' => null, 'failed_at' => null])->save();

        Bus::chain([new self($run->id), new RunSumoImport($run->id, dryRun: true)])
            ->onConnection(config('sumo-import.panel.queue_connection'))
            ->onQueue(config('sumo-import.panel.queue'))
            ->dispatch();
    }

    public function handle(): void
    {
        $run = SumoImportRun::find($this->runId);

        if ($run === null || $run->status === SumoImportRun::STATUS_CANCELLED) {
            $this->delete();

            return;
        }

        // Staging akan dikosongkan: tidak ada run lain yang datanya masih di sana.
        SumoImportRun::query()->where('staged', true)->update(['staged' => false]);
        $run->forceFill(['status' => SumoImportRun::STATUS_LOADING, 'staged' => false, 'source_tables' => null, 'dry_run_report' => null])->save();
        $run->appendLog("Memuat {$run->original_name} ke staging.");

        try {
            $tables = StoredFile::withLocalPath($run->file_disk, $run->file_path, fn (string $path): array => (new SumoDumpLoader((string) config('sumo-import.connection', 'sumo')))
                ->load($path, fn (string $line) => $run->appendLog($line)));

            $run->forceFill([
                'status' => SumoImportRun::STATUS_LOADED,
                'staged' => true,
                'source_tables' => $tables,
                'loaded_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $run->markFailed($exception);

            throw $exception;
        } finally {
            $run->flushLog();
        }
    }
}
