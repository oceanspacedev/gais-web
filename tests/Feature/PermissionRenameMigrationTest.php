<?php

namespace Tests\Feature;

use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Nama izin hanya punya satu format: bawaan Shield v4 `{Aksi}:{Subjek}`.
 * Migrasi rename mengganti nama baris lama di tempat supaya penugasan ke role
 * dan user tidak hilang.
 */
class PermissionRenameMigrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_names_are_renamed_in_place_and_keep_their_assignments(): void
    {
        $role = Role::findOrCreate('uji_rename_izin', 'web');
        $user = User::factory()->create();
        $legacy = [
            'view_any_uji::rename::izin' => 'ViewAny:UjiRenameIzin',
            'manage_business_entity_access_uji' => 'ManageBusinessEntityAccess:Uji',
            'widget_UjiRenameIzinChart' => 'View:UjiRenameIzinChart',
            'page_UjiRenameIzinPage' => 'View:UjiRenameIzinPage',
        ];
        $ids = [];

        foreach (array_keys($legacy) as $name) {
            $permission = Permission::create(['name' => $name, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
            $user->givePermissionTo($permission);
            $ids[$name] = $permission->id;
        }

        $this->migration()->up();

        foreach ($legacy as $old => $new) {
            $this->assertSame($new, DB::table('permissions')->where('id', $ids[$old])->value('name'));
            $this->assertTrue($role->fresh()->hasPermissionTo($new));
            $this->assertTrue($user->fresh()->hasDirectPermission($new));
        }
    }

    public function test_names_already_in_the_new_format_are_left_alone(): void
    {
        $before = Permission::query()->whereIn('name', ['Manage:SumoImport', 'View:TechnicalDocs'])->pluck('name', 'id')->all();

        $this->migration()->up();

        $this->assertNotEmpty($before);
        $this->assertEquals($before, Permission::query()->whereIn('id', array_keys($before))->pluck('name', 'id')->all());
    }

    public function test_a_legacy_row_is_merged_into_an_existing_row_of_the_new_name(): void
    {
        $viaOld = Role::findOrCreate('uji_rename_lama', 'web');
        $viaNew = Role::findOrCreate('uji_rename_baru', 'web');
        $old = Permission::create(['name' => 'export_uji::rename', 'guard_name' => 'web']);
        $new = Permission::create(['name' => 'Export:UjiRename', 'guard_name' => 'web']);
        $viaOld->givePermissionTo($old);
        $viaNew->givePermissionTo($new);
        // Role yang memegang keduanya tidak boleh membuat penggabungan gagal.
        $viaNew->givePermissionTo($old);

        $this->migration()->up();

        $this->assertNull(Permission::query()->find($old->id));
        $this->assertTrue($viaOld->fresh()->hasPermissionTo('Export:UjiRename'));
        $this->assertTrue($viaNew->fresh()->hasPermissionTo('Export:UjiRename'));
        $this->assertSame(1, Permission::query()->where('name', 'Export:UjiRename')->count());
    }

    public function test_rollback_restores_legacy_names_but_keeps_page_and_custom_permissions(): void
    {
        $resource = Permission::findOrCreate('ViewAny:AssetTransfer', 'web');
        $widget = Permission::findOrCreate('View:SumoOverviewStats', 'web');
        $custom = Permission::findOrCreate('Manage:SumoImport', 'web');
        $page = Permission::findOrCreate('View:TechnicalDocs', 'web');

        $this->migration()->down();

        $name = fn (Permission $permission): string => DB::table('permissions')->where('id', $permission->id)->value('name');
        $this->assertSame('view_any_asset::transfer', $name($resource));
        $this->assertSame('widget_SumoOverviewStats', $name($widget));
        $this->assertSame('Manage:SumoImport', $name($custom));
        $this->assertSame('View:TechnicalDocs', $name($page));
    }

    public function test_no_legacy_name_is_left_after_the_migrations(): void
    {
        $legacy = Permission::query()->pluck('name')
            ->reject(fn (string $name): bool => preg_match('/^[A-Z][A-Za-z0-9]*:[A-Z][A-Za-z0-9]*$/', $name) === 1)
            ->values()
            ->all();

        $this->assertSame([], $legacy, 'Nama izin di luar format {Aksi}:{Subjek} masih ada di database.');
    }

    public function test_every_key_shield_generates_uses_the_single_format(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $keys = collect(FilamentShield::getResources())
            ->flatMap(fn (array $resource): array => array_values(FilamentShield::getResourcePolicyActionsWithPermissions($resource['resourceFqcn'])))
            ->merge(collect(FilamentShield::getPages())->flatMap(fn (array $page): array => array_keys($page['permissions'])))
            ->merge(collect(FilamentShield::getWidgets())->flatMap(fn (array $widget): array => array_keys($widget['permissions'])))
            ->merge(array_keys(FilamentShield::getCustomPermissions()));

        $this->assertGreaterThan(400, $keys->count());
        $this->assertSame(
            [],
            $keys->reject(fn (string $key): bool => preg_match('/^[A-Z][A-Za-z0-9]*:[A-Z][A-Za-z0-9]*$/', $key) === 1)->values()->all(),
        );
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_16_200001_rename_permissions_to_shield_v4_format.php');
    }
}
