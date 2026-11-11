<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetServiceStatus;
use App\Enums\AssetTransferDocumentType;
use App\Models\Asset;
use App\Models\AssetService;
use App\Models\AssetTransfer;
use App\Models\Brand;
use App\Models\BusinessEntity;
use App\Models\Insurance;
use App\Models\ObChecksheet;
use App\Models\Rent;
use App\Models\Task;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AssertsVisibilityContract;
use Tests\TestCase;

/**
 * Kontrak App\Access untuk model di luar pengajuan dan laporan gangguan (yang
 * punya test sendiri): query visibleTo() dan isVisibleTo() harus sepakat,
 * termasuk untuk record terhapus.
 */
class VisibilityContractTest extends TestCase
{
    use AssertsVisibilityContract, DatabaseTransactions;

    private BusinessEntity $alpha;

    private BusinessEntity $beta;

    private User $restricted;

    private User $unrestricted;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->alpha = BusinessEntity::create(['name' => '__vc_alpha__']);
        $this->beta = BusinessEntity::create(['name' => '__vc_beta__']);
        $this->restricted = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $this->unrestricted = User::factory()->create(['business_entity_id' => $this->beta->id, 'access_all_business_entities' => true]);
    }

    public function test_assets_follow_owner_recipient_or_holder_entity(): void
    {
        $alphaEmployee = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $betaEmployee = User::factory()->create(['business_entity_id' => $this->beta->id]);

        $owned = $this->asset($this->alpha, $this->beta, $betaEmployee);
        $heldForAlpha = $this->asset($this->beta, $this->alpha, $betaEmployee);
        $usedByAlphaEmployee = $this->asset($this->beta, $this->beta, $alphaEmployee);
        $betaOnly = $this->asset($this->beta, $this->beta, $betaEmployee);
        $stock = Asset::create(['name' => '__vc_stock__', 'business_entity_id' => $this->beta->id]);
        $trashed = $this->asset($this->alpha, $this->alpha, $alphaEmployee);
        $trashed->delete();

        $ids = [$owned->id, $heldForAlpha->id, $usedByAlphaEmployee->id, $betaOnly->id, $stock->id, $trashed->id];

        $this->assertEqualsCanonicalizing(
            [$owned->id, $heldForAlpha->id, $usedByAlphaEmployee->id],
            Asset::query()->whereKey($ids)->visibleTo($this->restricted)->pluck('id')->all(),
        );
        $this->assertCount(5, Asset::query()->whereKey($ids)->visibleTo($this->unrestricted)->get());
        $this->assertTrue($trashed->isVisibleTo($this->restricted), 'Aset terhapus tetap dinilai dengan aturan yang sama (filter Sampah).');

        $this->assertQueryAndPolicyAgree(Asset::class, $this->viewers(), $ids, ['eager' => ['recipient'], 'lazy' => []]);
    }

    public function test_transfers_need_an_accessible_entity(): void
    {
        $from = User::factory()->create();
        $to = User::factory()->create();
        $alpha = $this->transfer($this->alpha, $from, $to);
        $beta = $this->transfer($this->beta, $from, $to);
        $trashed = $this->transfer($this->alpha, $from, $to);
        $trashed->delete();

        $ids = [$alpha->id, $beta->id, $trashed->id];

        $this->assertSame([$alpha->id], AssetTransfer::query()->whereKey($ids)->visibleTo($this->restricted)->pluck('id')->all());
        $this->assertQueryAndPolicyAgree(AssetTransfer::class, $this->viewers(), $ids);
    }

    public function test_contracts_and_tasks_follow_their_entity_and_allow_none(): void
    {
        $vendor = Vendor::create(['name' => '__vc_vendor__', 'last_price' => 0]);

        $insurances = [
            Insurance::create(['policy_number' => '__VC-A__', 'insured_name' => 'Alpha', 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'business_entity_id' => $this->alpha->id]),
            Insurance::create(['policy_number' => '__VC-B__', 'insured_name' => 'Beta', 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'business_entity_id' => $this->beta->id]),
            Insurance::create(['policy_number' => '__VC-N__', 'insured_name' => 'Tanpa BU', 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31']),
        ];
        $rents = [
            Rent::create(['rented_detail' => 'Alpha', 'first_party' => 'A', 'second_party' => 'B', 'rent_per_year' => 1, 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'business_entity_id' => $this->alpha->id]),
            Rent::create(['rented_detail' => 'Beta', 'first_party' => 'A', 'second_party' => 'B', 'rent_per_year' => 1, 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'business_entity_id' => $this->beta->id]),
        ];
        $tasks = [
            Task::create(['business_entity_id' => $this->alpha->id, 'vendor_id' => $vendor->id, 'work_timestamp' => now(), 'name' => 'Alpha', 'description' => '-', 'cost' => 1, 'location' => '-']),
            Task::create(['business_entity_id' => $this->beta->id, 'vendor_id' => $vendor->id, 'work_timestamp' => now(), 'name' => 'Beta', 'description' => '-', 'cost' => 1, 'location' => '-']),
        ];
        $tasks[1]->delete();

        $this->assertSame(
            [$insurances[0]->id, $insurances[2]->id],
            Insurance::query()->whereKey(array_map(fn (Insurance $i): int => $i->id, $insurances))->visibleTo($this->restricted)->orderBy('id')->pluck('id')->all(),
            'Kontrak tanpa badan usaha terlihat oleh semua.',
        );

        $this->assertQueryAndPolicyAgree(Insurance::class, $this->viewers(), array_map(fn (Insurance $i): int => $i->id, $insurances));
        $this->assertQueryAndPolicyAgree(Rent::class, $this->viewers(), array_map(fn (Rent $r): int => $r->id, $rents));
        $this->assertQueryAndPolicyAgree(Task::class, $this->viewers(), array_map(fn (Task $t): int => $t->id, $tasks));
    }

    public function test_users_see_themselves_and_their_entities_but_never_super_admins_unless_they_are_one(): void
    {
        $superAdminRole = Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web');
        $superAdmin = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $superAdmin->assignRole($superAdminRole);
        $alphaColleague = User::factory()->create(['business_entity_id' => $this->alpha->id]);
        $betaUser = User::factory()->create(['business_entity_id' => $this->beta->id]);
        $nobody = User::factory()->create();

        $ids = [$superAdmin->id, $alphaColleague->id, $betaUser->id, $nobody->id, $this->restricted->id, $this->unrestricted->id];

        $this->assertEqualsCanonicalizing(
            [$alphaColleague->id, $this->restricted->id],
            User::query()->whereKey($ids)->visibleTo($this->restricted)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$alphaColleague->id, $betaUser->id, $nobody->id, $this->restricted->id, $this->unrestricted->id],
            User::query()->whereKey($ids)->visibleTo($this->unrestricted)->pluck('id')->all(),
            'Akses semua badan usaha tetap tidak melihat super admin.',
        );
        $this->assertEqualsCanonicalizing(
            [$superAdmin->id, $alphaColleague->id, $this->restricted->id],
            User::query()->whereKey($ids)->visibleTo($superAdmin)->pluck('id')->all(),
            'Super admin tanpa akses semua badan usaha melihat badan usahanya sendiri, termasuk super admin lain.',
        );

        $this->assertQueryAndPolicyAgree(User::class, [...$this->viewers(), 'super admin' => $superAdmin], $ids, ['eager' => ['roles'], 'lazy' => []]);
    }

    public function test_ob_checksheets_are_own_unclaimed_or_all_with_permission(): void
    {
        $officer = User::factory()->create();
        $supervisor = User::factory()->create()->givePermissionTo(Permission::findOrCreate('view_all_ob::checksheet', 'web'));

        $own = ObChecksheet::create(['user_id' => $officer->id, 'room' => '__vc_own__', 'before_photo' => 'ob/a.jpg']);
        $others = ObChecksheet::create(['user_id' => User::factory()->create()->id, 'room' => '__vc_other__', 'before_photo' => 'ob/b.jpg']);
        $pool = ObChecksheet::create(['user_id' => null, 'source' => ObChecksheet::SOURCE_ASSIGNED, 'room' => '__vc_pool__', 'before_photo' => 'ob/c.jpg']);
        $ownerless = ObChecksheet::create(['user_id' => null, 'source' => ObChecksheet::SOURCE_INITIATIVE, 'room' => '__vc_none__', 'before_photo' => 'ob/d.jpg']);
        $trashed = ObChecksheet::create(['user_id' => $officer->id, 'room' => '__vc_trash__', 'before_photo' => 'ob/e.jpg']);
        $trashed->delete();

        $ids = [$own->id, $others->id, $pool->id, $ownerless->id, $trashed->id];

        $this->assertEqualsCanonicalizing([$own->id, $pool->id], ObChecksheet::query()->whereKey($ids)->visibleTo($officer)->pluck('id')->all());
        $this->assertCount(4, ObChecksheet::query()->whereKey($ids)->visibleTo($supervisor)->get());

        $this->assertQueryAndPolicyAgree(ObChecksheet::class, ['officer' => $officer, 'supervisor' => $supervisor, 'stranger' => User::factory()->create()], $ids);
    }

    public function test_asset_services_are_visible_to_creator_technician_or_view_all(): void
    {
        $creator = User::factory()->create();
        $technician = User::factory()->create();
        $supervisor = User::factory()->create()->givePermissionTo(Permission::findOrCreate('view_all_asset::service', 'web'));
        $asset = Asset::create(['name' => '__vc_service_asset__', 'business_entity_id' => $this->alpha->id]);

        $service = fn (User $createdBy, User $servicedBy): AssetService => AssetService::create([
            'asset_id' => $asset->id,
            'provider_type' => 'internal',
            'serviced_by_user_id' => $servicedBy->id,
            'service_date' => now()->toDateString(),
            'issue_description' => '__vc__',
            'status' => AssetServiceStatus::Pending->value,
            'created_by' => $createdBy->id,
        ]);

        $created = $service($creator, User::factory()->create());
        $assigned = $service(User::factory()->create(), $technician);
        $unrelated = $service(User::factory()->create(), User::factory()->create());
        $trashed = $service($creator, $creator);
        $trashed->delete();

        $ids = [$created->id, $assigned->id, $unrelated->id, $trashed->id];

        $this->assertSame([$created->id], AssetService::query()->whereKey($ids)->visibleTo($creator)->pluck('id')->all());
        $this->assertSame([$assigned->id], AssetService::query()->whereKey($ids)->visibleTo($technician)->pluck('id')->all());
        $this->assertCount(3, AssetService::query()->whereKey($ids)->visibleTo($supervisor)->get());

        $this->assertQueryAndPolicyAgree(AssetService::class, ['creator' => $creator, 'technician' => $technician, 'supervisor' => $supervisor], $ids);
    }

    public function test_master_data_is_visible_to_everyone_including_trashed_rows(): void
    {
        $brand = Brand::create(['name' => '__vc_brand__']);
        $trashed = Brand::create(['name' => '__vc_brand_trashed__']);
        $trashed->delete();

        $this->assertSame([$brand->id], Brand::query()->whereKey([$brand->id, $trashed->id])->visibleTo($this->restricted)->pluck('id')->all());
        $this->assertTrue($trashed->isVisibleTo($this->restricted));
        $this->assertQueryAndPolicyAgree(Brand::class, $this->viewers(), [$brand->id, $trashed->id]);
    }

    /**
     * @return array<string, User>
     */
    private function viewers(): array
    {
        return ['restricted' => $this->restricted, 'unrestricted' => $this->unrestricted, 'outsider' => User::factory()->create()];
    }

    private function asset(BusinessEntity $owner, BusinessEntity $recipientEntity, User $holder): Asset
    {
        return Asset::create([
            'name' => '__vc_asset_'.uniqid().'__',
            'business_entity_id' => $owner->id,
            'recipient_business_entity_id' => $recipientEntity->id,
            'recipient_id' => $holder->id,
            'condition_status' => AssetCondition::Transferred,
        ]);
    }

    private function transfer(BusinessEntity $entity, User $from, User $to): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $entity->id,
            'document_type' => AssetTransferDocumentType::PengalihanBarang,
            'letter_number' => '__VC/'.uniqid().'__',
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ]);
    }
}
