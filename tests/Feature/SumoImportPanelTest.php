<?php

namespace Tests\Feature;

use App\Filament\Resources\SumoImportRunResource;
use App\Filament\Resources\SumoImportRunResource\Pages\CreateSumoImportRun;
use App\Filament\Resources\SumoImportRunResource\Pages\ViewSumoImportRun;
use App\Models\BusinessEntity;
use App\Models\SumoImportRun;
use App\Models\User;
use App\Services\Sumo\SqlDumpReader;
use App\Services\Sumo\SumoDumpLoader;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SumoLegacyFixture;
use Tests\TestCase;

/**
 * Unggah dump sumo dari panel. Staging bawaan adalah tabel berawalan PREFIX di database
 * GAIS yang dipakai tes (dihapus di tearDown); tes mode lama memakai database MySQL
 * tersendiri STAGING (dibuat dan dihapus oleh tes ini).
 */
class SumoImportPanelTest extends TestCase
{
    use DatabaseTransactions;

    private const B = 9100000;

    private const STAGING = 'gais_sumo_panel_test';

    private const PREFIX = 'sumo_panel_test_';

    private string $scratch;

    /** @var array<string, mixed> */
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Storage::fake('local');
        config([
            'sumo-import.panel.disk' => 'local',
            'sumo-import.connection' => 'sumo',
            'database.connections.sumo' => ['prefix' => self::PREFIX] + config('database.connections.'.config('database.default')),
        ]);
        DB::purge('sumo');
        $this->server = config('database.connections.sumo');

        $this->scratch = sys_get_temp_dir().'/sumo-panel-test-'.uniqid();
        mkdir($this->scratch);
    }

    protected function tearDown(): void
    {
        DB::purge('sumo');
        array_map('unlink', glob($this->scratch.'/*'));
        rmdir($this->scratch);

        parent::tearDown();

        // Koneksi sendiri: DDL di koneksi GAIS akan meng-commit transaksi tes.
        $pdo = new \PDO("mysql:host={$this->server['host']};port={$this->server['port']}", $this->server['username'], $this->server['password']);
        $pdo->exec('DROP DATABASE IF EXISTS `'.self::STAGING.'`');
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        $tables = $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = '.$pdo->quote($this->server['database'])
            .' AND table_name LIKE '.$pdo->quote(str_replace('_', '\\_', self::PREFIX).'%'))->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `{$this->server['database']}`.`{$table}`");
        }
    }

    public function test_reader_splits_statements_like_the_mysql_client(): void
    {
        $sql = <<<'SQL'
            -- komentar baris
            # komentar hash
            /*!40101 SET NAMES utf8mb4 */;
            /* komentar blok; dengan titik koma */
            INSERT INTO `t` VALUES (1,'a;b','it\'s','dobel '' kutip','back\\slash'),(2,"x;y",'-- bukan komentar');
            CREATE TABLE `aneh;nama` (`id` int); -- komentar akhir
            DELIMITER ;;
            CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN SET @a = 1; SET @b = 2; END ;;
            DELIMITER ;
            SELECT 1-1
            SQL;

        $plain = $this->write('plain.sql', $sql);
        $gzip = $this->write('dump.sql.gz', gzencode($sql));

        $expected = [
            '/*!40101 SET NAMES utf8mb4 */',
            "INSERT INTO `t` VALUES (1,'a;b','it\\'s','dobel '' kutip','back\\\\slash'),(2,\"x;y\",'-- bukan komentar')",
            'CREATE TABLE `aneh;nama` (`id` int)',
            'CREATE TRIGGER tr BEFORE INSERT ON t FOR EACH ROW BEGIN SET @a = 1; SET @b = 2; END',
            'SELECT 1-1',
        ];

        $this->assertSame($expected, iterator_to_array((new SqlDumpReader($plain))->statements(), false));
        $this->assertSame($expected, iterator_to_array((new SqlDumpReader($gzip))->statements(), false));
    }

    public function test_reader_rejects_a_truncated_dump(): void
    {
        $this->expectException(RuntimeException::class);

        iterator_to_array((new SqlDumpReader($this->write('cut.sql', "INSERT INTO t VALUES ('belum ditutup")))->statements());
    }

    public function test_loader_skips_server_statements_and_rejects_writes_outside_staging(): void
    {
        $this->useSeparateStagingDatabase();
        $loader = new SumoDumpLoader('sumo');
        $gais = (string) config('database.connections.'.config('database.default').'.database');

        $this->assertNull($loader->prepare("USE `{$gais}`", self::STAGING));
        $this->assertNull($loader->prepare('CREATE DATABASE /*!32312 IF NOT EXISTS*/ `db_sumo`', self::STAGING));
        $this->assertNull($loader->prepare("/*!80000 SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'abc:1-9' */", self::STAGING));
        $this->assertSame('INSERT INTO `'.self::STAGING.'`.`users` VALUES (1)', $loader->prepare('INSERT INTO `'.self::STAGING.'`.`users` VALUES (1)', self::STAGING));
        $this->assertSame("INSERT INTO `users` VALUES ('a.b')", $loader->prepare("INSERT INTO `users` VALUES ('a.b')", self::STAGING));
        $this->assertSame(
            '/*!50001 CREATE ALGORITHM=UNDEFINED */ /*!50013 SQL SECURITY DEFINER */ /*!50001 VIEW `v` AS select 1 */',
            $loader->prepare('/*!50001 CREATE ALGORITHM=UNDEFINED */ /*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */ /*!50001 VIEW `v` AS select 1 */', self::STAGING),
        );

        foreach ([
            "INSERT INTO `{$gais}`.`users` VALUES (1)",
            "DROP TABLE IF EXISTS {$gais}.roles",
            'DROP DATABASE `db_sumo`',
            "GRANT ALL ON *.* TO 'x'@'%'",
            "SELECT * FROM users INTO OUTFILE '/tmp/x'",
        ] as $sql) {
            try {
                $loader->prepare($sql, self::STAGING);
                $this->fail("Seharusnya ditolak: {$sql}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_loader_prefixes_every_table_when_staging_shares_the_gais_database(): void
    {
        $loader = new SumoDumpLoader('sumo');
        $gais = (string) config('database.connections.'.config('database.default').'.database');
        $p = self::PREFIX;

        $this->assertSame("DROP TABLE IF EXISTS `{$p}users`", $loader->prepare('DROP TABLE IF EXISTS `users`', $gais));
        $this->assertSame("/*!50001 DROP TABLE IF EXISTS `{$p}v`*/", $loader->prepare('/*!50001 DROP TABLE IF EXISTS `v`*/', $gais));
        $this->assertSame("LOCK TABLES `{$p}users` WRITE", $loader->prepare('LOCK TABLES `users` WRITE', $gais));
        $this->assertSame("/*!40000 ALTER TABLE `{$p}users` DISABLE KEYS */", $loader->prepare('/*!40000 ALTER TABLE `users` DISABLE KEYS */', $gais));
        $this->assertSame("INSERT INTO `{$p}users` (`id`,`username`) VALUES (1,'users'),(2,'roles')", $loader->prepare("INSERT INTO `users` (`id`,`username`) VALUES (1,'users'),(2,'roles')", $gais));
        $this->assertSame("INSERT INTO `{$p}users` VALUES (1)", $loader->prepare('INSERT INTO users VALUES (1)', $gais));
        $this->assertSame('/*!40101 SET NAMES utf8mb4 */', $loader->prepare('/*!40101 SET NAMES utf8mb4 */', $gais));
        $this->assertSame('UNLOCK TABLES', $loader->prepare('UNLOCK TABLES', $gais));

        // Nama constraint unik per database dan REFERENCES menunjuk tabel GAIS: dibuang.
        $this->assertSame(
            "CREATE TABLE `{$p}requests` (\n  `id` bigint NOT NULL,\n  `user_id` bigint DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `requests_user_id_foreign` (`user_id`)\n) ENGINE=InnoDB",
            $loader->prepare("CREATE TABLE `requests` (\n  `id` bigint NOT NULL,\n  `user_id` bigint DEFAULT NULL,\n  PRIMARY KEY (`id`),\n  KEY `requests_user_id_foreign` (`user_id`),\n  CONSTRAINT `requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,\n  CONSTRAINT `requests_chk_1` CHECK (json_valid(`id`))\n) ENGINE=InnoDB", $gais),
        );

        foreach ([
            'ALTER TABLE `requests` ADD CONSTRAINT `fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
            '/*!50001 CREATE ALGORITHM=UNDEFINED */ /*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */ /*!50001 VIEW `v` AS select 1 */',
            '/*!50001 DROP VIEW IF EXISTS `v`*/',
            '/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `tr` BEFORE INSERT ON `users` FOR EACH ROW SET NEW.id = 1 */',
            '/*!50003 DROP PROCEDURE IF EXISTS `p` */',
            '/*!50717 PREPARE s FROM @rocksdb_get_is_supported */',
        ] as $sql) {
            $this->assertNull($loader->prepare($sql, $gais), "Seharusnya dilewati: {$sql}");
        }

        foreach ([
            "INSERT INTO `{$gais}`.`users` VALUES (1)",
            'DROP TABLE IF EXISTS `users`, `roles`',
            'INSERT INTO `users` SELECT * FROM `roles`',
            'INSERT `users` VALUES (1)',
            'UPDATE users SET username = NULL',
            'DELETE FROM users',
            'RENAME TABLE `a` TO `users`',
            'ALTER TABLE `a` RENAME TO `users`',
            'CREATE TABLE `a` LIKE `users`',
            'LOCK TABLES `a` WRITE, `users` WRITE',
            "CREATE TABLE `a` (`id` int, CONSTRAINT `f` FOREIGN KEY (`id`) REFERENCES `users` (`id`))",
        ] as $sql) {
            try {
                $loader->prepare($sql, $gais);
                $this->fail("Seharusnya ditolak: {$sql}");
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_loader_needs_a_prefix_that_no_gais_table_uses_in_the_gais_database(): void
    {
        config(['database.connections.sumo.prefix' => '']);

        try {
            (new SumoDumpLoader('sumo'))->load($this->write('dump.sql', SumoLegacyFixture::mysqlDump()));
            $this->fail('Tanpa awalan, database GAIS harus ditolak.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('butuh awalan tabel', $exception->getMessage());
        }

        // sumo_import_runs adalah tabel GAIS: awalan `sumo_` akan ikut menghapusnya.
        config(['database.connections.sumo.prefix' => 'sumo_']);

        $this->expectExceptionMessage('juga dipakai tabel GAIS `sumo_import_runs`');

        (new SumoDumpLoader('sumo'))->load($this->write('dump.sql', SumoLegacyFixture::mysqlDump()));
    }

    public function test_separate_staging_database_still_loads_without_prefix(): void
    {
        $this->useSeparateStagingDatabase();

        $tables = (new SumoDumpLoader('sumo'))->load($this->write('dump.sql', SumoLegacyFixture::mysqlDump($this->legacyRows())));

        $this->assertSame(2, $tables['users']);
        $this->assertSame(self::STAGING, DB::connection('sumo')->getDatabaseName());
        $this->assertSame(2, DB::connection('sumo')->table('users')->count());
    }

    public function test_cli_loads_a_dump_file_before_importing(): void
    {
        $this->configureMapping(BusinessEntity::create(['name' => 'PT Uji Panel Sumo', 'format' => 'UPS']));
        $users = User::count();

        $this->artisan('sumo:import', ['--file' => $this->write('dump.sql.gz', gzencode(SumoLegacyFixture::mysqlDump($this->legacyRows()))), '--dry-run' => true])
            ->expectsOutputToContain('Membuat tabel users')
            ->assertSuccessful();

        $this->assertSame(2, DB::connection('sumo')->table('users')->count());
        $this->assertSame($users, User::count());
    }

    public function test_upload_loads_staging_dry_runs_and_imports_after_confirmation(): void
    {
        $entity = BusinessEntity::create(['name' => 'PT Uji Panel Sumo', 'format' => 'UPS']);
        $this->configureMapping($entity);
        $admin = $this->actingAsImporter();

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm([
                'file_path' => UploadedFile::fake()->createWithContent('dump-sumo.sql.gz', gzencode(SumoLegacyFixture::mysqlDump($this->legacyRows()))),
                'notes' => 'Dump uji',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $run = SumoImportRun::latest('id')->firstOrFail();

        $this->assertSame(SumoImportRun::STATUS_READY, $run->status, (string) $run->error);
        $this->assertTrue($run->staged);
        $this->assertSame('dump-sumo.sql.gz', $run->original_name);
        $this->assertSame($admin->id, $run->user_id);
        $this->assertSame(2, $run->source_tables['users']);
        $this->assertSame(2, $run->dry_run_report['counts']['users.created']);
        $this->assertStringContainsString('Membuat tabel users', $run->log);
        $this->assertStringContainsString('Uji coba selesai', $run->log);
        // `DROP TABLE users` di dump mengenai tabel berawalan, bukan tabel users GAIS. Dibaca lewat
        // koneksi sumo: snapshot transaksi tes lebih tua dari tabel itu.
        $this->assertSame(2, DB::connection('sumo')->table('users')->count());
        $this->assertSame(self::PREFIX.'users', DB::connection('sumo')->getTablePrefix().'users');
        // Uji coba dibatalkan: belum ada user sumo di GAIS.
        $this->assertSame(0, User::where('legacy_sumo_user_id', self::B + 3)->count());

        Livewire::test(ViewSumoImportRun::class, ['record' => $run->getRouteKey()])
            ->callAction('import', ['confirmation' => 'impor'])
            ->assertHasActionErrors(['confirmation']);

        $this->assertSame(SumoImportRun::STATUS_READY, $run->fresh()->status);

        Livewire::test(ViewSumoImportRun::class, ['record' => $run->getRouteKey()])
            ->callAction('import', ['confirmation' => 'IMPOR'])
            ->assertHasNoActionErrors();

        $run->refresh();
        $this->assertSame(SumoImportRun::STATUS_IMPORTED, $run->status, (string) $run->error);
        $this->assertSame($admin->id, $run->imported_by_id);
        $this->assertSame(2, $run->import_report['counts']['users.created']);
        $requester = User::where('legacy_sumo_user_id', self::B + 3)->firstOrFail();
        $this->assertSame($entity->id, $requester->business_entity_id);
        $this->assertTrue(Hash::check('rahasia', $requester->password));
    }

    public function test_dump_writing_into_the_gais_database_fails_without_touching_gais(): void
    {
        $this->configureMapping(BusinessEntity::create(['name' => 'PT Uji Panel Sumo', 'format' => 'UPS']));
        $this->actingAsImporter();
        $gais = (string) config('database.connections.'.config('database.default').'.database');
        $users = User::count();

        $dump = SumoLegacyFixture::mysqlDump($this->legacyRows())."DROP TABLE IF EXISTS `{$gais}`.`users`;\n";

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('jahat.sql', $dump)])
            ->call('create');

        $run = SumoImportRun::latest('id')->firstOrFail();

        $this->assertSame(SumoImportRun::STATUS_FAILED, $run->status);
        $this->assertFalse($run->staged);
        $this->assertStringContainsString("nama database (`{$gais}`)", $run->error);
        $this->assertSame($users, User::count());
        $this->assertFalse($run->canImport());
    }

    public function test_upload_accepts_a_gzip_dump_named_only_gz(): void
    {
        $this->configureMapping(BusinessEntity::create(['name' => 'PT Uji Panel Sumo', 'format' => 'UPS']));
        $this->actingAsImporter();

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('sumo-27-okt.gz', gzencode(SumoLegacyFixture::mysqlDump($this->legacyRows())))])
            ->call('create')
            ->assertHasNoFormErrors();

        $run = SumoImportRun::latest('id')->firstOrFail();

        $this->assertSame('sumo-27-okt.gz', $run->original_name);
        $this->assertSame(SumoImportRun::STATUS_READY, $run->status, (string) $run->error);
    }

    public function test_upload_rejects_files_that_are_not_sql_dumps(): void
    {
        $this->actingAsImporter();
        $before = SumoImportRun::count();

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('catatan.txt', 'bukan dump')])
            ->call('create');

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('palsu.sql.gz', 'CREATE TABLE x (id int);')])
            ->call('create');

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('palsu.gz', 'CREATE TABLE x (id int);')])
            ->call('create');

        $this->assertSame($before, SumoImportRun::count());
    }

    public function test_menu_requires_the_sumo_import_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole(Role::findOrCreate('uji_tanpa_impor_sumo', 'web'));
        $this->actingAs($outsider);

        $this->get(SumoImportRunResource::getUrl('index'))->assertForbidden();

        $this->actingAsImporter();

        $this->get(SumoImportRunResource::getUrl('index'))->assertOk();
    }

    /**
     * Mode lama: database staging tersendiri tanpa awalan tabel.
     */
    private function useSeparateStagingDatabase(): void
    {
        config(['database.connections.sumo' => ['database' => self::STAGING, 'prefix' => ''] + config('database.connections.sumo')]);
        DB::purge('sumo');
    }

    private function actingAsImporter(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('uji_impor_sumo', 'web')->givePermissionTo(Permission::findOrCreate(SumoImportRun::PERMISSION, 'web')));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $user;
    }

    private function configureMapping(BusinessEntity $entity): void
    {
        config([
            'sumo-import.business_entities' => [self::B + 1 => null, self::B + 2 => $entity->id],
            'sumo-import.scopes' => [],
            'sumo-import.division_permissions' => [],
            'sumo-import.legacy_user_permissions' => [],
            'sumo-import.user_links' => [],
        ]);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function legacyRows(): array
    {
        $b = self::B;
        $hash = Hash::make('rahasia', ['rounds' => 4]);
        $user = fn (int $id, string $username, string $name, int $entity, int $role, int $approval): array => [
            'id' => $id, 'username' => $username, 'fullname' => $name, 'password' => $hash, 'badan_usaha_id' => $entity,
            'division_id' => $b + 6, 'role_id' => $role, 'approval_id' => $approval,
        ];

        return [
            'areas' => [['id' => $b + 2, 'area' => 'HO;PANEL']],
            'badan_usahas' => [['id' => $b + 1, 'badan_usaha' => '-'], ['id' => $b + 2, 'badan_usaha' => "PT O'PANEL"]],
            'divisions' => [['id' => $b + 6, 'division' => 'HCM-PANEL', 'area_id' => $b + 2]],
            'users' => [
                $user($b + 1, 'admin-panel-uji', 'ADMIN PANEL UJI', $b + 1, 1, 0),
                $user($b + 3, 'cs-panel-uji', 'CS PANEL UJI', $b + 2, 4, $b + 1),
            ],
        ];
    }

    private function write(string $name, string $content): string
    {
        file_put_contents($path = "{$this->scratch}/{$name}", $content);

        return $path;
    }
}
