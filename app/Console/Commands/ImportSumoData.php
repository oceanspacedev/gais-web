<?php

namespace App\Console\Commands;

use App\Models\SumoImportRun;
use App\Services\Sumo\SumoDumpLoader;
use App\Services\Sumo\SumoImporter;
use Illuminate\Console\Command;

class ImportSumoData extends Command
{
    protected $signature = 'sumo:import
        {--file= : Muat dump .sql atau .sql.gz ini ke staging dulu, seperti unggah di panel}
        {--dry-run : Jalankan di dalam transaksi lalu batalkan; hanya menampilkan laporan}
        {--only=* : Langkah tertentu saja: areas, divisions, masters, users, requests, problems, insurances, rents, wa, scopes, access}';

    protected $description = 'Impor data legacy sumo-web (Web Gais lama) dari koneksi `sumo` ke tabel GAIS. Bisa diulang.';

    public function handle(): int
    {
        $only = array_values(array_filter((array) $this->option('only')));
        $unknown = array_diff($only, SumoImporter::STEPS);

        if ($unknown !== []) {
            $this->error('Langkah tidak dikenal: '.implode(', ', $unknown).'. Pilihan: '.implode(', ', SumoImporter::STEPS));

            return self::INVALID;
        }

        $connection = (string) config('sumo-import.connection', 'sumo');
        $file = $this->option('file');

        if ($file !== null) {
            if (! is_file($file)) {
                $this->error("File tidak ditemukan: {$file}");

                return self::INVALID;
            }

            if (SumoImportRun::anyBusy()) {
                $this->error('Masih ada impor dari panel yang antre atau berjalan. Tunggu sampai selesai.');

                return self::FAILURE;
            }

            // Isi staging diganti: run panel yang dump-nya ada di sana harus dimuat ulang.
            SumoImportRun::query()->where('staged', true)->update(['staged' => false]);
            $this->info("Memuat {$file} ke staging...");
            (new SumoDumpLoader($connection))->load($file, fn (string $line) => $this->line("  {$line}"));
            $this->newLine();
        }

        $importer = new SumoImporter($connection);
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? 'Mode uji coba: perubahan akan dibatalkan di akhir.' : 'Mengimpor data sumo...');

        $report = $importer->runInTransaction($only === [] ? null : $only, $dryRun, fn (string $line) => $this->line("  {$line}"));

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
