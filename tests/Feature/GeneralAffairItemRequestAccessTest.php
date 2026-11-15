<?php

namespace Tests\Feature;

use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Filament\Resources\ItemRequestResource\Pages\ViewItemRequest;
use App\Models\BusinessEntity;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Staf GA (role general_affair, bukan akun sumo, tanpa divisi bercakupan dan
 * tanpa akses semua badan usaha) harus melihat menu dan data Pengajuan Barang
 * di badan usahanya.
 */
class GeneralAffairItemRequestAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Role dibuat manual di server; pastikan ada, lalu jalankan migrasinya di transaksi tes.
        Role::findOrCreate('general_affair', 'web');
        (require database_path('migrations/2026_11_16_000001_grant_item_request_permissions_to_general_affair.php'))->up();

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
}
