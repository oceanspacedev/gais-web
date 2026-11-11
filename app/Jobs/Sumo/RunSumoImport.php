<?php

namespace App\Jobs\Sumo;

use App\Models\SumoImportRun;
use App\Services\Sumo\SumoImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Menjalankan `SumoImporter` terhadap dump yang sedang ada di staging. Uji coba
 * dibatalkan di akhir transaksi; impor sungguhan hanya untuk run yang uji
 * cobanya sudah selesai.
 */
class RunSumoImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId, public bool $dryRun = true, public ?int $userId = null)
    {
        $this->onConnection(config('sumo-import.panel.queue_connection'));
        $this->onQueue(config('sumo-import.panel.queue'));
    }

    public static function dispatchFor(SumoImportRun $run, bool $dryRun, ?int $userId = null): void
    {
        $run->forceFill(['status' => SumoImportRun::STATUS_QUEUED, 'error' => null, 'failed_at' => null])->save();

        dispatch(new self($run->id, $dryRun, $userId));
    }

    public function handle(): void
    {
        $run = SumoImportRun::find($this->runId);

        if ($run === null || $run->status === SumoImportRun::STATUS_CANCELLED) {
            $this->delete();

            return;
        }

        try {
            // Run lain bisa saja memuat dump-nya ke staging di antara unggah dan job ini.
            if (! $run->staged) {
                throw new RuntimeException('Dump run ini sudah tidak ada di staging. Muat ulang dulu.');
            }

            if (! $this->dryRun && $run->dry_run_report === null) {
                throw new RuntimeException('Impor sungguhan butuh uji coba yang selesai untuk dump ini.');
            }

            $run->forceFill(['status' => $this->dryRun ? SumoImportRun::STATUS_DRY_RUNNING : SumoImportRun::STATUS_IMPORTING])->save();
            $run->appendLog($this->dryRun ? 'Uji coba dimulai (semua perubahan dibatalkan di akhir).' : 'Impor sungguhan dimulai.');

            $startedAt = microtime(true);
            $report = (new SumoImporter((string) config('sumo-import.connection', 'sumo')))
                ->runInTransaction(null, $this->dryRun, fn (string $line) => $run->appendLog($line));
            $result = $report->toArray() + ['seconds' => round(microtime(true) - $startedAt, 1), 'finished_at' => now()->toIso8601String()];

            $run->appendLog(sprintf('%s selesai dalam %.1f detik: %d peringatan.', $this->dryRun ? 'Uji coba' : 'Impor', $result['seconds'], count($result['warnings'])));

            $run->forceFill($this->dryRun
                ? ['status' => SumoImportRun::STATUS_READY, 'dry_run_report' => $result, 'dry_run_at' => now()]
                : ['status' => SumoImportRun::STATUS_IMPORTED, 'import_report' => $result, 'imported_at' => now(), 'imported_by_id' => $this->userId]
            )->save();
        } catch (Throwable $exception) {
            $run->markFailed($exception);

            throw $exception;
        } finally {
            $run->flushLog();
        }
    }
}
