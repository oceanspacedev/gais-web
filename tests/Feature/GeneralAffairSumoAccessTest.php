<?php

namespace Tests\Feature;

use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Filament\Resources\ItemRequestResource\Pages\ViewItemRequest;
use App\Filament\Resources\ItemRequestTypeResource;
use App\Filament\Resources\ProblemReportResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\RentResource;
use App\Models\BusinessEntity;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staf GA (role general_affair) adalah ADMIN di sumo lama: memegang hak penuh
 * modul sumo, role pengajuan_admin digabung ke general_affair, dan data tetap
 * dibatasi badan usaha.
 */
class GeneralAffairSumoAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> izin role ADMIN sumo (pengajuan_admin) sebelum digabung */
    private array $sumoAdminPermissions;

    private User $formerSumoAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Kondisi server sebelum deploy: role pengajuan_admin hasil impor sumo masih ada
        // dan dipakai, general_affair (dibuat manual) belum punya izin sumo.
        (require database_path('migrations/2026_10_13_000007_create_sumo_module_permissions_and_roles.php'))->up();
        // Migrasi itu masih membuat nama izin format lama; rename (migrasi terakhir) merapikannya.
        // Dua migrasi berikutnya menyalin izin antar-role tanpa menyebut nama, jadi urutannya setara.
        (require database_path('migrations/2026_11_16_200001_rename_permissions_to_shield_v4_format.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->sumoAdminPermissions = Role::findByName('pengajuan_admin', 'web')->permissions->pluck('name')->all();
        $this->formerSumoAdmin = User::factory()->create();
        $this->formerSumoAdmin->assignRole('pengajuan_admin');
        Role::findOrCreate('general_affair', 'web')->syncPermissions([]);

        (require database_path('migrations/2026_11_16_000001_grant_sumo_admin_permissions_to_general_affair.php'))->up();
        (require database_path('migrations/2026_11_16_000003_merge_pengajuan_admin_role_into_general_affair.php'))->up();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_general_affair_without_full_access_sees_item_requests_of_their_business_entity(): void
    {
        $home = BusinessEntity::create(['name' => '__ga_home__']);
        $other = BusinessEntity::create(['name' => '__ga_other__']);
        $type = ItemRequestType::create(['name' => 'ATK-ga', 'requires_products' => true]);

        $staff = User::factory()->create([
            'division_id' => null,
            'business_entity_id' => $home->id,
            'access_all_business_entities' => false,
        ]);
        $staff->assignRole('general_affair');

        $requester = User::factory()->create();
        $relevant = ItemRequest::create(['user_id' => $requester->id, 'item_request_type_id' => $type->id, 'requested_at' => now(), 'business_entity_id' => $home->id]);
        $foreign = ItemRequest::create(['user_id' => $requester->id, 'item_request_type_id' => $type->id, 'requested_at' => now(), 'business_entity_id' => $other->id]);

        $this->actingAs($staff);

        $this->assertTrue(ItemRequestResource::canViewAny(), 'Menu Pengajuan Barang harus tampil untuk general_affair.');
        $this->assertTrue($staff->can('view', $relevant));
        $this->assertFalse($staff->can('view', $foreign));
        $this->assertTrue($staff->can('process', ItemRequest::class));

        Livewire::test(ListItemRequests::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$relevant])
            ->assertCanNotSeeTableRecords([$foreign]);

        Livewire::test(ViewItemRequest::class, ['record' => $relevant->getRouteKey()])->assertOk();
    }

    public function test_general_affair_holds_every_sumo_admin_permission(): void
    {
        $ga = Role::findByName('general_affair', 'web')->permissions->pluck('name');

        $this->assertNotEmpty($this->sumoAdminPermissions);
        $this->assertSame([], collect($this->sumoAdminPermissions)->diff($ga)->values()->all(), 'general_affair harus memegang semua izin ADMIN sumo.');

        $staff = User::factory()->create(['access_all_business_entities' => true]);
        $staff->assignRole('general_affair');
        $this->actingAs($staff);

        foreach ([
            ProblemReportResource::class,
            InsuranceResource::class,
            RentResource::class,
            ProductResource::class,
            ItemRequestTypeResource::class,
        ] as $resource) {
            $this->assertTrue($resource::canViewAny(), class_basename($resource).' harus tampil untuk general_affair.');
            $this->assertTrue($resource::canCreate(), class_basename($resource).' harus bisa dibuat oleh general_affair.');
        }

        $this->assertTrue($staff->can('approveAccounting', ItemRequest::class));
        $this->assertTrue($staff->can('audit', ItemRequest::class));
    }

    public function test_pengajuan_admin_users_become_general_affair_and_the_role_is_removed(): void
    {
        $this->assertNull(Role::query()->where('name', 'pengajuan_admin')->where('guard_name', 'web')->first());

        $user = $this->formerSumoAdmin->fresh();
        $this->assertTrue($user->hasRole('general_affair'));
        $this->assertTrue($user->can('Import:Insurance'));
        $this->assertSame('general_affair', config('sumo-import.roles.1'), 'Impor sumo memetakan ADMIN ke general_affair.');
    }

    public function test_rollback_only_revokes_sumo_permissions(): void
    {
        $role = Role::findByName('general_affair', 'web');
        $role->givePermissionTo(Permission::findOrCreate('ViewAny:Asset', 'web'));

        // Urutan rollback sungguhan: rename dibatalkan dulu, baru migrasi di bawahnya.
        $rename = require database_path('migrations/2026_11_16_200001_rename_permissions_to_shield_v4_format.php');
        $rename->down();
        (require database_path('migrations/2026_11_16_000001_grant_sumo_admin_permissions_to_general_affair.php'))->down();
        $rename->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $names = $role->fresh()->permissions->pluck('name');
        $this->assertContains('ViewAny:Asset', $names);
        $this->assertNotContains('ViewAny:ItemRequest', $names);
        $this->assertNotContains('ViewAny:ProblemReport', $names);
    }
}
