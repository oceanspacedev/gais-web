<?php

namespace Tests\Feature;

use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource;
use App\Filament\Resources\TaskResource\Pages\ListTasks;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\AssetTransferDetail;
use App\Models\BusinessEntity;
use App\Models\Insurance;
use App\Models\InsuranceUpdate;
use App\Models\ItemRequest;
use App\Models\ItemRequestDetail;
use App\Models\ItemRequestType;
use App\Models\Task;
use App\Models\User;
use App\Models\VehicleChecksheet;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Konsekuensi soft delete yang dulu ditangani cascade hard delete: record di
 * Sampah hanya-baca, tidak lagi menjadi riwayat yang berlaku, tidak tampil di
 * QR publik, dan hapus permanen mengikuti izin yang benar.
 */
class SoftDeleteBehaviourTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_public_qr_of_a_trashed_asset_is_invalid(): void
    {
        $asset = Asset::factory()->create();
        $qr = $asset->fresh()->qr;

        $this->get(route('qr.show', $qr))->assertOk();

        $asset->delete();

        $this->get(route('qr.show', $qr))->assertNotFound();
        $this->post(route('qr.location', $qr), ['latitude' => -6.2, 'longitude' => 106.8])->assertNotFound();
        $this->assertSame(1, $qr->scans()->count(), 'Pemindaian aset di Sampah tidak dicatat.');
    }

    public function test_trashed_records_are_read_only_until_restored(): void
    {
        $entity = BusinessEntity::create(['name' => '__sd_entity__']);
        $user = $this->userWith(['view_asset::transfer', 'update_asset::transfer', 'delete_asset::transfer', 'restore_asset::transfer', 'force_delete_asset::transfer', 'replicate_asset::transfer'], $entity);
        $transfer = $this->transfer($entity);

        $this->assertTrue($user->can('update', $transfer));

        $transfer->delete();
        $transfer = $transfer->fresh();

        $this->assertTrue($user->can('view', $transfer), 'Record di Sampah tetap boleh dilihat.');
        $this->assertTrue($user->can('restore', $transfer));
        $this->assertTrue($user->can('forceDelete', $transfer));
        $this->assertFalse($user->can('update', $transfer), 'Record di Sampah tidak boleh diubah.');
        $this->assertFalse($user->can('delete', $transfer));
        $this->assertFalse($user->can('replicate', $transfer));

        $this->actingAs($user);
        $this->assertFalse(AssetTransferResource::canEdit($transfer));
        $this->assertTrue(AssetTransferResource::canView($transfer));

        $transfer->restore();
        $this->assertTrue($user->can('update', $transfer->fresh()));
    }

    public function test_child_force_delete_follows_the_parent_force_delete_permission(): void
    {
        $insurance = Insurance::create(['policy_number' => '__SD-POL__', 'insured_name' => 'SD', 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31']);
        $update = InsuranceUpdate::create(['insurance_id' => $insurance->id, 'policy_number' => '__SD-POL-2__', 'join_date' => '2026-01-01', 'expired_date' => '2027-12-31']);
        $editor = $this->userWith(['view_insurance', 'update_insurance']);
        $purger = $this->userWith(['view_insurance', 'update_insurance', 'force_delete_insurance', 'force_delete_any_insurance']);

        $this->assertTrue($editor->can('delete', $update));
        $this->assertTrue($editor->can('restore', $update));
        $this->assertFalse($editor->can('forceDelete', $update), 'Izin ubah saja tidak cukup untuk hapus permanen.');
        $this->assertFalse($editor->can('forceDeleteAny', InsuranceUpdate::class));
        $this->assertTrue($purger->can('forceDelete', $update));
        $this->assertTrue($purger->can('forceDeleteAny', InsuranceUpdate::class));
    }

    public function test_a_trashed_transfer_is_no_longer_the_assets_latest_transfer(): void
    {
        $entity = BusinessEntity::create(['name' => '__sd_latest__']);
        $asset = Asset::create(['name' => '__sd_asset__', 'business_entity_id' => $entity->id]);
        $first = $this->transfer($entity);
        $second = $this->transfer($entity);
        AssetTransferDetail::create(['asset_transfer_id' => $first->id, 'asset_id' => $asset->id]);
        AssetTransferDetail::create(['asset_transfer_id' => $second->id, 'asset_id' => $asset->id, 'created_at' => now()->addMinute()]);

        $this->assertSame($second->id, $asset->latestTransferDetail()?->asset_transfer_id);

        $second->delete();

        $this->assertSame($first->id, $asset->fresh()->latestTransferDetail()?->asset_transfer_id, 'BA di Sampah bukan riwayat yang berlaku.');
    }

    public function test_vehicle_checksheet_keeps_its_files_on_soft_delete_and_removes_them_on_force_delete(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('vehicle/depart.jpg', 'x');
        $asset = Asset::create(['name' => '__sd_vehicle__']);
        $checksheet = VehicleChecksheet::create(['asset_id' => $asset->id, 'reference_number' => '__SD-REF__', 'license_plate' => 'B 1 SD', 'departure_photo' => 'vehicle/depart.jpg']);

        $checksheet->delete();
        Storage::disk('public')->assertExists('vehicle/depart.jpg');

        $checksheet->restore();
        $checksheet->forceDelete();
        Storage::disk('public')->assertMissing('vehicle/depart.jpg');
    }

    public function test_shield_form_lists_trash_permissions_and_super_admin_may_force_delete(): void
    {
        foreach (['restore', 'restoreAny', 'forceDelete', 'forceDeleteAny'] as $method) {
            $this->assertContains($method, config('filament-shield.policies.methods'), "Izin {$method} harus tampil di form Role Shield.");
        }

        $superAdmin = Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web');
        $this->assertTrue($superAdmin->hasPermissionTo('force_delete_asset'), 'Migrasi memberi hapus permanen ke role super admin.');
    }

    public function test_custom_record_actions_are_hidden_for_trashed_rows(): void
    {
        $role = Role::findOrCreate('__sd_all__', 'web');
        $role->syncPermissions(Permission::all());
        $viewer = User::factory()->create(['access_all_business_entities' => true]);
        $viewer->assignRole($role);
        $this->actingAs($viewer->fresh());

        $entity = BusinessEntity::create(['name' => '__sd_task_entity__']);
        $vendor = Vendor::create(['name' => '__sd_vendor__', 'last_price' => 0]);
        $task = Task::create(['business_entity_id' => $entity->id, 'vendor_id' => $vendor->id, 'work_timestamp' => now(), 'name' => 'SD', 'description' => '-', 'cost' => 1, 'location' => '-']);
        $trashed = Task::create(['business_entity_id' => $entity->id, 'vendor_id' => $vendor->id, 'work_timestamp' => now(), 'name' => 'SD2', 'description' => '-', 'cost' => 1, 'location' => '-']);
        $trashed->delete();

        Livewire::test(ListTasks::class)
            ->filterTable('trashed', true)
            ->assertTableActionVisible('process', $task)
            ->assertTableActionHidden('process', $trashed)
            ->assertTableActionHidden('edit', $trashed)
            ->assertTableActionVisible('restore', $trashed);
    }

    public function test_detail_of_a_trashed_request_is_read_only_but_restorable(): void
    {
        $type = ItemRequestType::create(['name' => '__sd_type__', 'requires_products' => true]);
        $owner = User::factory()->create();
        $request = ItemRequest::create(['user_id' => $owner->id, 'item_request_type_id' => $type->id, 'requested_at' => now()]);
        $detail = ItemRequestDetail::create(['item_request_id' => $request->id, 'qty_request' => 1]);

        $this->assertTrue($owner->can('update', $detail));

        $detail->delete();
        $this->assertFalse($owner->fresh()->can('update', $detail->fresh()), 'Barang di Sampah tidak boleh diubah.');
        $this->assertTrue($owner->fresh()->can('restore', $detail->fresh()));
        $detail->restore();

        $request->delete();
        $this->assertFalse($owner->fresh()->can('update', $detail->fresh()), 'Barang dari pengajuan di Sampah tidak boleh diubah.');
    }

    public function test_task_codes_are_never_reused_after_a_soft_delete(): void
    {
        $entity = BusinessEntity::create(['name' => 'SDCODE']);
        $vendor = Vendor::create(['name' => '__sd_vendor_code__', 'last_price' => 0]);
        $make = fn (): Task => Task::create(['business_entity_id' => $entity->id, 'vendor_id' => $vendor->id, 'work_timestamp' => now(), 'name' => 'SD', 'description' => '-', 'cost' => 1, 'location' => '-']);

        $first = $make();
        $first->delete();
        $second = $make();

        $this->assertNotSame($first->code, $second->code);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, ?BusinessEntity $entity = null): User
    {
        $user = User::factory()->create(['business_entity_id' => $entity?->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user->fresh();
    }

    private function transfer(BusinessEntity $entity): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'document_type' => AssetTransferDocumentType::PengalihanBarang,
            'letter_number' => '__SD/'.uniqid().'__',
            'from_user_id' => User::factory()->create()->id,
            'to_user_id' => User::factory()->create()->id,
            'transfer_date' => now()->toDateString(),
        ]);
    }
}
