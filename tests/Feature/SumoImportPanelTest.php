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
 * Unggah dump sumo dari panel. Database staging adalah database MySQL tersendiri
 * (dibuat dan dihapus oleh tes ini), terpisah dari database shelf yang dipakai tes.
 */
class SumoImportPanelTest extends TestCase
{
    use DatabaseTransactions;

    private const B = 9100000;

    private const STAGING = 'shelf_sumo_panel_test';

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
            'database.connections.sumo' => ['database' => self::STAGING] + config('database.connections.'.config('database.default')),
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

        // Koneksi sendiri: DROP DATABASE di koneksi shelf akan meng-commit transaksi tes.
        (new \PDO("mysql:host={$this->server['host']};port={$this->server['port']}", $this->server['username'], $this->server['password']))
            ->exec('DROP DATABASE IF EXISTS `'.self::STAGING.'`');
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
        $loader = new SumoDumpLoader('sumo');
        $shelf = (string) config('database.connections.'.config('database.default').'.database');

        $this->assertNull($loader->prepare("USE `{$shelf}`", self::STAGING));
        $this->assertNull($loader->prepare('CREATE DATABASE /*!32312 IF NOT EXISTS*/ `db_sumo`', self::STAGING));
        $this->assertNull($loader->prepare("/*!80000 SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ 'abc:1-9' */", self::STAGING));
        $this->assertSame('INSERT INTO `'.self::STAGING.'`.`users` VALUES (1)', $loader->prepare('INSERT INTO `'.self::STAGING.'`.`users` VALUES (1)', self::STAGING));
        $this->assertSame("INSERT INTO `users` VALUES ('a.b')", $loader->prepare("INSERT INTO `users` VALUES ('a.b')", self::STAGING));
        $this->assertSame(
            '/*!50001 CREATE ALGORITHM=UNDEFINED */ /*!50013 SQL SECURITY DEFINER */ /*!50001 VIEW `v` AS select 1 */',
            $loader->prepare('/*!50001 CREATE ALGORITHM=UNDEFINED */ /*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */ /*!50001 VIEW `v` AS select 1 */', self::STAGING),
        );

        foreach ([
            "INSERT INTO `{$shelf}`.`users` VALUES (1)",
            "DROP TABLE IF EXISTS {$shelf}.roles",
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

    public function test_loader_refuses_the_shelf_database_as_staging(): void
    {
        config(['database.connections.sumo.database' => config('database.connections.'.config('database.default').'.database')]);

        $this->expectExceptionMessage('tidak boleh kosong atau sama dengan database shelf');

        (new SumoDumpLoader('sumo'))->load($this->write('dump.sql', SumoLegacyFixture::mysqlDump()));
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
        // Uji coba dibatalkan: belum ada user sumo di shelf.
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

    public function test_dump_writing_into_the_shelf_database_fails_without_touching_shelf(): void
    {
        $this->configureMapping(BusinessEntity::create(['name' => 'PT Uji Panel Sumo', 'format' => 'UPS']));
        $this->actingAsImporter();
        $shelf = (string) config('database.connections.'.config('database.default').'.database');
        $users = User::count();

        $dump = SumoLegacyFixture::mysqlDump($this->legacyRows())."DROP TABLE IF EXISTS `{$shelf}`.`users`;\n";

        Livewire::test(CreateSumoImportRun::class)
            ->fillForm(['file_path' => UploadedFile::fake()->createWithContent('jahat.sql', $dump)])
            ->call('create');

        $run = SumoImportRun::latest('id')->firstOrFail();

        $this->assertSame(SumoImportRun::STATUS_FAILED, $run->status);
        $this->assertFalse($run->staged);
        $this->assertStringContainsString("database lain (`{$shelf}`)", $run->error);
        $this->assertSame($users, User::count());
        $this->assertFalse($run->canImport());
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
