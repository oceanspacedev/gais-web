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
        $this->loadUserMap();

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
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $update  kolom yang diperbarui bila id sudah ada
     */
    protected function upsert(string $table, array $rows, array $update, string $countKey): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, ['id'], $update);
            $this->report->add($countKey, count($chunk));
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
