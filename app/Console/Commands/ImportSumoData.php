<?php

namespace App\Console\Commands;

use App\Services\Sumo\SumoImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class ImportSumoData extends Command
{
    protected $signature = 'sumo:import
        {--dry-run : Jalankan di dalam transaksi lalu batalkan; hanya menampilkan laporan}
        {--only=* : Langkah tertentu saja: areas, divisions, masters, users, requests, problems, insurances, rents, wa, scopes, access}';

    protected $description = 'Impor data legacy sumo-web (Web Gais) dari koneksi `sumo` ke tabel shelf. Bisa diulang.';

    public function handle(): int
    {
        $only = array_values(array_filter((array) $this->option('only')));
        $unknown = array_diff($only, SumoImporter::STEPS);

        if ($unknown !== []) {
            $this->error('Langkah tidak dikenal: '.implode(', ', $unknown).'. Pilihan: '.implode(', ', SumoImporter::STEPS));

            return self::INVALID;
        }

        $importer = new SumoImporter((string) config('sumo-import.connection', 'sumo'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Mode uji coba: perubahan akan dibatalkan di akhir.' : 'Mengimpor data sumo...');

        DB::beginTransaction();

        try {
            $report = $importer->run($only === [] ? null : $only, fn (string $line) => $this->line("  {$line}"));

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->newLine();
        $this->table(['Langkah', 'Jumlah'], collect($report->counts())->map(fn (int $count, string $key): array => [$key, $count])->values()->all());

        if ($report->notes() !== []) {
            $this->newLine();
            $this->info('Pemetaan (periksa sebelum impor sungguhan):');

            foreach ($report->notes() as $note) {
                $this->line("  - {$note}");
            }
        }

        if ($report->warnings() !== []) {
            $this->newLine();
            $this->warn('Peringatan ('.count($report->warnings()).'):');

            foreach ($report->warnings() as $warning) {
                $this->line("  - {$warning}");
            }
        }

        $this->info($dryRun ? 'Uji coba selesai, tidak ada yang disimpan.' : 'Impor selesai.');

        return self::SUCCESS;
    }
}
