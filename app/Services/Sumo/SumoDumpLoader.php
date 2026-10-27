<?php

namespace App\Services\Sumo;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Memuat dump sumo ke database staging (koneksi `sumo`) supaya bisa dibaca
 * `SumoImporter`. Dump sumo memakai DROP TABLE untuk tabel yang namanya sama
 * dengan shelf (users, roles, migrations, ...), jadi loader menolak target yang
 * sama dengan database shelf dan menolak pernyataan yang keluar dari database
 * staging (USE, nama tabel berkualifikasi database lain, GRANT, dsb.).
 */
class SumoDumpLoader
{
    /** Tabel sumo yang dibaca importer; dump tanpa salah satunya ditolak. */
    public const REQUIRED_TABLES = [
        'areas', 'badan_usahas', 'divisions', 'users', 'products', 'request_type', 'request_settings',
        'requests', 'request_details', 'request_approvals', 'request_logs', 'problem_report',
        'insurances', 'insurance_updates', 'rents', 'rent_updates', 'wa_notification_logs', 'wa_notification_settings',
    ];

    /** Dilewati: hanya relevan untuk server asal dump. */
    private const SKIPPED = '/^(USE\b|CREATE\s+(DATABASE|SCHEMA)\b|SET\s+@@GLOBAL\.|SET\s+@@SESSION\.SQL_LOG_BIN\b)/';

    /** Ditolak: menyentuh hal di luar isi database staging. */
    private const FORBIDDEN = '/^(DROP\s+(DATABASE|SCHEMA)|GRANT|REVOKE|(CREATE|DROP|ALTER|RENAME)\s+USER|SET\s+PASSWORD|CHANGE\s+(MASTER|REPLICATION)|START\s+(SLAVE|REPLICA)|LOAD\s+(DATA|XML)|FLUSH|SHUTDOWN|KILL|(UN)?INSTALL\s+(PLUGIN|COMPONENT)|HANDLER|SELECT\b.*\bINTO\s+(OUT|DUMP)FILE)\b/s';

    /** Pernyataan yang menulis ke satu tabel target; target dicek tidak berkualifikasi database lain. */
    private const TARGETED = '/^(?:INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+(?:TEMPORARY\s+)?TABLE(?:\s+IF\s+NOT\s+EXISTS)?|ALTER\s+TABLE|TRUNCATE(?:\s+TABLE)?|LOCK\s+TABLES|UPDATE|DELETE\s+FROM|RENAME\s+TABLE)\s+((?:`[^`]*`|[^\s`(,]+)(?:\s*\.\s*(?:`[^`]*`|[^\s`(,]+))?)/i';

    private ?Closure $logger = null;

    public function __construct(private readonly string $connection = 'sumo') {}

    /**
     * Kosongkan database staging lalu jalankan dump. Mengembalikan jumlah baris per tabel.
     *
     * @return array<string, int>
     */
    public function load(string $path, ?Closure $logger = null): array
    {
        $this->logger = $logger;
        $database = $this->guardTarget();
        $this->ensureDatabase($database);

        $db = DB::connection($this->connection);
        $this->guardNotShelf($db, $database);

        $this->log("Mengosongkan database staging `{$database}`.");
        $db->getSchemaBuilder()->dropAllViews();
        $db->getSchemaBuilder()->dropAllTables();

        $executed = 0;
        $skipped = 0;

        try {
            foreach ((new SqlDumpReader($path))->statements() as $number => $sql) {
                $prepared = $this->prepare($sql, $database);

                if ($prepared === null) {
                    $skipped++;

                    continue;
                }

                try {
                    $db->unprepared($prepared);
                } catch (Throwable $exception) {
                    throw new RuntimeException('Pernyataan ke-'.($number + 1).' gagal: '.$exception->getMessage().' | '.mb_strimwidth($prepared, 0, 300, '...'), 0, $exception);
                }

                $executed++;

                if (preg_match('/^CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?([^`\s(]+)/i', ltrim($prepared), $match)) {
                    $this->log("Membuat tabel {$match[1]}");
                }
            }
        } finally {
            // Dump mengubah variabel sesi (FOREIGN_KEY_CHECKS, SQL_MODE, ...); importer butuh sesi bersih.
            DB::purge($this->connection);
        }

        $this->log("{$executed} pernyataan dijalankan, {$skipped} dilewati.");

        $tables = $this->tableCounts();
        $missing = array_values(array_diff(self::REQUIRED_TABLES, array_keys($tables)));

        if ($missing !== []) {
            throw new RuntimeException('Dump ini bukan dump sumo lengkap. Tabel yang tidak ada: '.implode(', ', $missing).'.');
        }

        $this->log(count($tables).' tabel, '.array_sum($tables).' baris dimuat.');

        return $tables;
    }

    /**
     * @return array<string, int>
     */
    public function tableCounts(): array
    {
        $db = DB::connection($this->connection);
        $counts = [];

        foreach ($db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), false) as $table) {
            $counts[$table] = (int) $db->table($table)->count();
        }

        ksort($counts);

        return $counts;
    }

    /**
     * Pernyataan siap dijalankan, null bila dilewati. Melempar bila berbahaya.
     */
    public function prepare(string $sql, string $database): ?string
    {
        $head = $this->head($sql);

        if (preg_match(self::SKIPPED, $head)) {
            $this->log('Dilewati: '.mb_strimwidth($head, 0, 80, '...'));

            return null;
        }

        if (preg_match(self::FORBIDDEN, $head)) {
            throw new RuntimeException('Dump berisi pernyataan yang tidak diizinkan: '.mb_strimwidth($head, 0, 120, '...'));
        }

        if (preg_match(self::TARGETED, $head, $match) && str_contains($this->unquoted($match[1]), '.')) {
            [$schema] = explode('.', $this->unquoted($match[1]), 2);

            if (trim($schema) !== strtolower($database)) {
                throw new RuntimeException("Dump menulis ke database lain (`{$schema}`): ".mb_strimwidth($head, 0, 120, '...'));
            }
        }

        if (preg_match('/^(INSERT|REPLACE)\b/', $head)) {
            return $sql;
        }

        // DEFINER pada view/trigger butuh hak SUPER; objeknya dibuat atas nama user koneksi.
        return preg_replace('/\s*DEFINER\s*=\s*(`[^`]*`|\'[^\']*\'|[^\s@]+)@(`[^`]*`|\'[^\']*\'|[^\s*]+)/i', '', $sql);
    }

    /**
     * Huruf besar dari awal pernyataan, tanpa pembuka komentar eksekusi `/*!40101`.
     */
    private function head(string $sql): string
    {
        $sql = ltrim($sql);

        if (preg_match('/^\/\*!\d*\s*/', $sql, $match)) {
            $sql = substr($sql, strlen($match[0]));
        }

        return strtoupper(substr($sql, 0, 2000));
    }

    /**
     * Nama tabel tanpa backtick; titik di dalam backtick bukan pemisah database.
     */
    private function unquoted(string $name): string
    {
        return preg_replace_callback('/`([^`]*)`/', fn (array $part): string => str_replace('.', '', strtolower($part[1])), strtolower($name));
    }

    private function guardTarget(): string
    {
        $target = (array) config("database.connections.{$this->connection}");
        $shelfName = (string) config('database.default');
        $shelf = (array) config("database.connections.{$shelfName}");
        $database = (string) ($target['database'] ?? '');

        if ($this->connection === $shelfName || ! in_array($target['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Koneksi staging `{$this->connection}` harus koneksi MySQL tersendiri, bukan koneksi utama shelf.");
        }

        if ($database === '' || strcasecmp($database, (string) ($shelf['database'] ?? '')) === 0) {
            throw new RuntimeException("Database staging (`{$database}`) tidak boleh kosong atau sama dengan database shelf. Isi SUMO_DB_DATABASE dengan database tersendiri, mis. db_sumo.");
        }

        return $database;
    }

    /**
     * Database staging yang ternyata berisi tabel migrasi shelf tidak akan dikosongkan.
     */
    private function guardNotShelf(Connection $db, string $database): void
    {
        $schema = $db->getSchemaBuilder();

        if ($schema->hasTable('migrations') && $db->table('migrations')->where('migration', 'like', '%create_asset_transfers_table')->exists()) {
            throw new RuntimeException("Database `{$database}` berisi tabel shelf (migrasi create_asset_transfers_table). Pilih database staging lain di SUMO_DB_DATABASE.");
        }
    }

    /**
     * Buat database staging bila belum ada, lewat koneksi tanpa database terpilih.
     * Bila user database tidak punya hak CREATE, database harus dibuat manual.
     */
    private function ensureDatabase(string $database): void
    {
        $bootstrap = "{$this->connection}__bootstrap";

        config(["database.connections.{$bootstrap}" => ['database' => null] + (array) config("database.connections.{$this->connection}")]);

        try {
            DB::connection($bootstrap)->statement('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (Throwable $exception) {
            $this->log('Tidak bisa membuat database staging otomatis ('.$exception->getMessage().'); memakai database yang sudah ada.');
        } finally {
            DB::purge($bootstrap);
            config(["database.connections.{$bootstrap}" => null]);
        }
    }

    private function log(string $line): void
    {
        if ($this->logger !== null) {
            ($this->logger)($line);
        }
    }
}
