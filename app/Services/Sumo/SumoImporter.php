<?php

namespace App\Services\Sumo;

use App\Services\Sumo\Concerns\ImportsContracts;
use App\Services\Sumo\Concerns\ImportsItemRequests;
use App\Services\Sumo\Concerns\ImportsMasterData;
use App\Services\Sumo\Concerns\ImportsProblemReports;
use App\Services\Sumo\Concerns\ImportsUsers;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Memindahkan data sumo-web (koneksi `sumo`) ke tabel shelf. Setiap langkah
 * idempoten: baris dikenali dari id sumo (dipertahankan) atau legacy_sumo_user_id,
 * jadi perintah boleh diulang setelah dump sumo diperbarui.
 */
class SumoImporter
{
    use ImportsContracts;
    use ImportsItemRequests;
    use ImportsMasterData;
    use ImportsProblemReports;
    use ImportsUsers;

    public const STEPS = ['areas', 'divisions', 'masters', 'users', 'requests', 'problems', 'insurances', 'rents', 'wa', 'scopes', 'access'];

    protected SumoImportReport $report;

    protected ?Closure $logger = null;

    /** @var array<int, int> id user sumo => id user shelf */
    protected array $userMap = [];

    /** @var array<int, int|null> id user shelf => business_entity_id */
    protected array $userEntity = [];

    /**
     * User shelf yang baru dibuat atau baru ditautkan di run ini; hanya mereka yang
     * diberi role/izin sumo (null = langkah users tidak dijalankan, semua user sumo).
     *
     * @var array<int, true>|null
     */
    protected ?array $accessTargets = null;

    /** Belum ada user sumo yang tertaut sebelum run ini. */
    protected bool $firstImport = true;

    /** @var array<string, array<int, true>> */
    private array $idSets = [];

    public function __construct(protected readonly string $connection = 'sumo') {}

    /**
     * @param  list<string>|null  $only  langkah yang dijalankan (null = semua, urut STEPS)
     */
    public function run(?array $only = null, ?Closure $logger = null): SumoImportReport
    {
        $this->report = new SumoImportReport;
        $this->logger = $logger;
        $this->idSets = [];
        $this->accessTargets = null;
        $this->loadUserMap();
        $this->firstImport = $this->userMap === [];

        $methods = [
            'areas' => 'importAreas',
            'divisions' => 'importDivisions',
            'masters' => 'importMasters',
            'users' => 'importUsers',
            'requests' => 'importItemRequests',
            'problems' => 'importProblemReports',
            'insurances' => 'importInsurances',
            'rents' => 'importRents',
            'wa' => 'importWaLogs',
            'scopes' => 'seedScopes',
            'access' => 'assignAccess',
        ];

        foreach ($methods as $step => $method) {
            if ($only !== null && ! in_array($step, $only, true)) {
                continue;
            }

            $this->log("[{$step}]");
            $this->{$method}();
        }

        return $this->report;
    }

    /**
     * Jalankan impor dalam satu transaksi shelf. Uji coba dibatalkan di akhir,
     * jadi laporannya sama dengan impor sungguhan tanpa ada yang tersimpan.
     *
     * @param  list<string>|null  $only
     */
    public function runInTransaction(?array $only = null, bool $dryRun = false, ?Closure $logger = null): SumoImportReport
    {
        DB::beginTransaction();

        try {
            $report = $this->run($only, $logger);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $report;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    protected function source(string $table): Builder
    {
        return DB::connection($this->connection)->table($table);
    }

    protected function log(string $line): void
    {
        if ($this->logger !== null) {
            ($this->logger)($line);
        }
    }

    /**
     * Id yang sudah ada di tabel shelf, untuk menjaga FK saat memetakan.
     */
    protected function has(string $table, mixed $id): bool
    {
        if ($id === null || $id === '' || (int) $id <= 0) {
            return false;
        }

        $this->idSets[$table] ??= array_fill_keys(DB::table($table)->pluck('id')->map(fn ($value): int => (int) $value)->all(), true);

        return isset($this->idSets[$table][(int) $id]);
    }

    protected function forgetIds(string $table): void
    {
        unset($this->idSets[$table]);
    }

    protected function idOrNull(string $table, mixed $id): ?int
    {
        return $this->has($table, $id) ? (int) $id : null;
    }

    /**
     * Hanya baris yang id-nya belum ada di shelf yang ditulis: impor ulang tidak
     * menimpa perubahan yang dibuat di shelf sesudah impor pertama (status yang
     * sudah diproses, pengaturan yang diubah, dst.), hanya menambah baris baru.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $update  kolom yang diperbarui bila baris baru bentrok di unique key lain
     */
    protected function upsert(string $table, array $rows, array $update, string $countKey): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            $existing = DB::table($table)->whereIn('id', array_column($chunk, 'id'))->pluck('id')
                ->mapWithKeys(fn ($id): array => [(int) $id => true])->all();
            $new = array_values(array_filter($chunk, fn (array $row): bool => ! isset($existing[(int) $row['id']])));

            if ($new !== []) {
                DB::table($table)->upsert($new, ['id'], $update);
                $this->report->add($countKey, count($new));
            }

            if ($existing !== []) {
                $this->report->add("{$countKey}.existing", count($existing));
            }
        }

        $this->forgetIds($table);
    }

    protected function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' || ! is_numeric($value) ? null : (int) $value;
    }

    /**
     * Tanggal sumo bisa kosong, "0000-00-00", atau tidak valid; semuanya jadi null.
     */
    protected function dateOrNull(mixed $value, bool $dateOnly = false): ?string
    {
        $value = $this->str($value);

        if ($value === null || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            $date = Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }

        if ($date->year < 1900) {
            return null;
        }

        return $dateOnly ? $date->toDateString() : $date->format('Y-m-d H:i:s');
    }

    /**
     * @return array<string, string|null>
     */
    protected function timestamps(object $row, bool $softDeletes = true): array
    {
        $stamps = [
            'created_at' => $this->dateOrNull($row->created_at ?? null) ?? now()->format('Y-m-d H:i:s'),
            'updated_at' => $this->dateOrNull($row->updated_at ?? null) ?? now()->format('Y-m-d H:i:s'),
        ];

        if ($softDeletes) {
            $stamps['deleted_at'] = $this->dateOrNull($row->deleted_at ?? null);
        }

        return $stamps;
    }
}
