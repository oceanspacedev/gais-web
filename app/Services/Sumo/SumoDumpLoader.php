<?php

namespace App\Services\Sumo;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Memuat dump sumo ke staging (koneksi `sumo`) supaya bisa dibaca `SumoImporter`.
 *
 * Bawaannya staging adalah tabel berawalan SUMO_DB_PREFIX (`sumo_dump_`) di database
 * GAIS: nama setiap tabel di dump diberi awalan, jadi `DROP TABLE users` di dump mengenai
 * `sumo_dump_users`, bukan tabel GAIS. Hanya pernyataan tabel yang dikenali yang dijalankan;
 * view, trigger, dan routine dilewati. Tanpa awalan, staging harus database tersendiri yang
 * dikosongkan seluruhnya. Di kedua cara, pernyataan yang keluar dari staging (USE, nama
 * tabel berkualifikasi database, GRANT, dsb.) ditolak.
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

    /** Dengan awalan: pernyataan tabel yang dijalankan, dengan nama tabelnya (grup 2) diberi awalan. */
    private const PREFIXABLE = '/^(\s*(?:\/\*!\d*\s*)?(DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|LOCK\s+TABLES|ALTER\s+TABLE|TRUNCATE(?:\s+TABLE)?)\s+)(`(?:[^`]|``)+`|[\w$]+)/i';

    /** Dengan awalan: pernyataan tabel yang bentuknya tidak dikenali, jadi ditolak, bukan dilewati. */
    private const UNSUPPORTED = '/^(INSERT|REPLACE|UPDATE|DELETE|RENAME|TRUNCATE|LOCK|CALL|(CREATE|DROP|ALTER)\s+(TEMPORARY\s+)?TABLE)\b/';

    /** Dengan awalan: sesi dan transaksi, dijalankan apa adanya. */
    private const SESSION = '/^(SET\b|UNLOCK\s+TABLES\b|START\s+TRANSACTION\b|BEGIN\b|COMMIT\b)/';

    private ?Closure $logger = null;

    public function __construct(private readonly string $connection = 'sumo') {}

    /**
     * Kosongkan staging lalu jalankan dump. Mengembalikan jumlah baris per tabel (tanpa awalan).
     *
     * @return array<string, int>
     */
    public function load(string $path, ?Closure $logger = null): array
    {
        $this->logger = $logger;
        $database = $this->guardTarget();

        if (! $this->sharesGaisDatabase()) {
            $this->ensureDatabase($database);
        }

        $db = DB::connection($this->connection);
        $this->clear($db, $database);

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
                    $this->log('Membuat tabel '.substr($match[1], strlen($this->prefix())));
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

        foreach ($this->stagedTables($db) as $table) {
            $name = substr($table, strlen($this->prefix()));
            $counts[$name] = (int) $db->table($name)->count();
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

            if ($this->prefix() !== '') {
                throw new RuntimeException("Dump menulis ke tabel dengan nama database (`{$schema}`); nama tabel di dump harus tanpa database: ".mb_strimwidth($head, 0, 120, '...'));
            }

            if (trim($schema) !== strtolower($database)) {
                throw new RuntimeException("Dump menulis ke database lain (`{$schema}`): ".mb_strimwidth($head, 0, 120, '...'));
            }
        }

        if ($this->prefix() !== '') {
            return $this->prepareWithPrefix($sql, $head);
        }

        if (preg_match('/^(INSERT|REPLACE)\b/', $head)) {
            return $sql;
        }

        // DEFINER pada view/trigger butuh hak SUPER; objeknya dibuat atas nama user koneksi.
        return preg_replace('/\s*DEFINER\s*=\s*(`[^`]*`|\'[^\']*\'|[^\s@]+)@(`[^`]*`|\'[^\']*\'|[^\s*]+)/i', '', $sql);
    }

    /**
     * Staging berbagi database dengan GAIS: hanya pernyataan tabel yang dikenali yang
     * dijalankan, dengan nama tabel diberi awalan.
     */
    private function prepareWithPrefix(string $sql, string $head): ?string
    {
        if (preg_match(self::SESSION, $head)) {
            return $sql;
        }

        if (! preg_match(self::PREFIXABLE, $sql, $match)) {
            if (preg_match(self::UNSUPPORTED, $head)) {
                throw new RuntimeException('Dump berisi pernyataan tabel yang tidak didukung: '.mb_strimwidth($head, 0, 120, '...'));
            }

            // View, trigger, routine, event, ALTER DATABASE, PREPARE/EXECUTE, ...: importer hanya membaca tabel.
            $this->log('Dilewati: '.mb_strimwidth($head, 0, 80, '...'));

            return null;
        }

        $keyword = strtoupper(preg_replace('/\s+/', ' ', $match[2]));
        $rest = substr($sql, strlen($match[0]), 65536);
        $supported = match (true) {
            str_starts_with($keyword, 'DROP'), str_starts_with($keyword, 'TRUNCATE') => (bool) preg_match('/^\s*(\*\/\s*)?$/', $rest),
            str_starts_with($keyword, 'LOCK') => (bool) preg_match('/^\s*(READ|WRITE|LOW_PRIORITY)\b[^,]*$/i', $rest),
            str_starts_with($keyword, 'CREATE') => str_starts_with(ltrim($rest), '('),
            str_starts_with($keyword, 'ALTER') => ! preg_match('/\b(RENAME|WITH\s+TABLE)\b/i', $rest),
            default => (bool) preg_match('/^\s*(\([^)]*\))?\s*VALUES?\b/i', $rest),
        };

        if (! $supported) {
            throw new RuntimeException('Dump berisi pernyataan tabel yang tidak didukung: '.mb_strimwidth($head, 0, 120, '...'));
        }

        // Foreign key menunjuk tabel tanpa awalan (tabel GAIS); staging hanya dibaca, jadi tidak perlu.
        if (str_starts_with($keyword, 'ALTER') && preg_match('/\b(FOREIGN\s+KEY|REFERENCES)\b/i', $rest)) {
            $this->log('Dilewati (foreign key): '.mb_strimwidth($head, 0, 80, '...'));

            return null;
        }

        $sql = substr_replace($sql, $this->prefixed($match[3]), strlen($match[1]), strlen($match[3]));

        if (str_starts_with($keyword, 'CREATE')) {
            $sql = $this->withoutConstraints($sql);

            if (preg_match('/\bFOREIGN\s+KEY\b/i', $sql)) {
                throw new RuntimeException('Foreign key di CREATE TABLE tidak bisa dilepas: '.mb_strimwidth($head, 0, 120, '...'));
            }
        }

        return $sql;
    }

    /**
     * Buang baris FOREIGN KEY dan CHECK bernama dari CREATE TABLE: nama constraint unik
     * per database (bisa bentrok dengan GAIS) dan REFERENCES menunjuk tabel tanpa awalan.
     */
    private function withoutConstraints(string $sql): string
    {
        return preg_replace('/,\s*\n[ \t]*(?:CONSTRAINT\s+(?:`(?:[^`]|``)*`|\S+)\s+)?(?:FOREIGN\s+KEY|CHECK)\b[^\n]*?(?=,?\s*\n)/i', '', $sql);
    }

    private function prefixed(string $identifier): string
    {
        $name = str_starts_with($identifier, '`') ? str_replace('``', '`', substr($identifier, 1, -1)) : $identifier;

        return '`'.str_replace('`', '``', $this->prefix().$name).'`';
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

    private function prefix(): string
    {
        return (string) config("database.connections.{$this->connection}.prefix", '');
    }

    private function sharesGaisDatabase(): bool
    {
        $gais = (string) config('database.connections.'.config('database.default').'.database');

        return strcasecmp((string) config("database.connections.{$this->connection}.database"), $gais) === 0;
    }

    private function guardTarget(): string
    {
        $target = (array) config("database.connections.{$this->connection}");
        $gaisName = (string) config('database.default');
        $database = (string) ($target['database'] ?? '');

        if ($this->connection === $gaisName || ! in_array($target['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Koneksi staging `{$this->connection}` harus koneksi MySQL tersendiri, bukan koneksi utama GAIS.");
        }

        if ($database === '') {
            throw new RuntimeException('Database staging sumo kosong. Isi SUMO_DB_DATABASE, atau kosongkan supaya memakai database GAIS dengan awalan SUMO_DB_PREFIX.');
        }

        if (! $this->sharesGaisDatabase()) {
            return $database;
        }

        $prefix = $this->prefix();

        if ($prefix === '') {
            throw new RuntimeException("Staging sumo di database GAIS (`{$database}`) butuh awalan tabel supaya tabel GAIS tidak tertimpa. Isi SUMO_DB_PREFIX, mis. sumo_dump_.");
        }

        foreach ($this->gaisTables() as $table) {
            if (str_starts_with($table, $prefix)) {
                throw new RuntimeException("Awalan SUMO_DB_PREFIX `{$prefix}` juga dipakai tabel GAIS `{$table}`. Pilih awalan lain, mis. sumo_dump_.");
            }
        }

        return $database;
    }

    /**
     * Tabel yang dibuat migrasi GAIS (`create_x_table`), supaya awalan staging tidak mengenainya.
     *
     * @return list<string>
     */
    private function gaisTables(): array
    {
        return DB::table('migrations')->pluck('migration')
            ->map(fn (string $migration): ?string => preg_match('/create_(\w+?)_table$/', $migration, $match) ? $match[1] : null)
            ->filter()
            ->push('migrations')
            ->values()
            ->all();
    }

    /**
     * Hapus isi staging: tabel berawalan, atau seluruh database staging bila tanpa awalan.
     */
    private function clear(Connection $db, string $database): void
    {
        $prefix = $this->prefix();

        if ($prefix === '') {
            $this->guardNotGais($db, $database);
            $this->log("Mengosongkan database staging `{$database}`.");
            $db->getSchemaBuilder()->dropAllViews();
            $db->getSchemaBuilder()->dropAllTables();

            return;
        }

        $this->log("Menghapus tabel staging `{$prefix}*` di database `{$database}`.");
        $db->getSchemaBuilder()->withoutForeignKeyConstraints(function () use ($db): void {
            foreach ($this->stagedTables($db) as $table) {
                $db->statement('DROP TABLE IF EXISTS `'.str_replace('`', '``', $table).'`');
            }
        });
    }

    /**
     * Nama lengkap (dengan awalan) tabel staging di database koneksi.
     *
     * @return list<string>
     */
    private function stagedTables(Connection $db): array
    {
        $prefix = $this->prefix();

        return array_values(array_filter(
            $db->getSchemaBuilder()->getTableListing($db->getDatabaseName(), false),
            fn (string $table): bool => $prefix === '' || str_starts_with($table, $prefix),
        ));
    }

    /**
     * Database staging yang ternyata berisi tabel migrasi GAIS tidak akan dikosongkan.
     */
    private function guardNotGais(Connection $db, string $database): void
    {
        $schema = $db->getSchemaBuilder();

        if ($schema->hasTable('migrations') && $db->table('migrations')->where('migration', 'like', '%create_asset_transfers_table')->exists()) {
            throw new RuntimeException("Database `{$database}` berisi tabel GAIS (migrasi create_asset_transfers_table). Pilih database staging lain di SUMO_DB_DATABASE.");
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
