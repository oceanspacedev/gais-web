<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetTransferDocumentType;
use App\Enums\ItemRequestApprovalType;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\AssetTransfersRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\HeldAssetsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ItemRequestApprovalsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ItemRequestsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ProblemReportsRelationManager;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\BusinessEntity;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\ProblemReport;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tab relasi di halaman user: isinya mengikuti aturan lihat menu aslinya,
 * jadi halaman user tidak membuka data yang tidak boleh dilihat viewer.
 */
class UserRelationTabsTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessEntity $home;

    private BusinessEntity $other;

    private User $owner;

    private ItemRequest $ownRequest;

    private ItemRequest $approvedRequest;

    private ProblemReport $report;

    private Asset $heldAsset;

    private AssetTransfer $sent;

    private AssetTransfer $received;

    private AssetTransfer $unrelated;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->home = BusinessEntity::create(['name' => 'PT Tab Relasi', 'format' => 'TRL']);
        $this->other = BusinessEntity::create(['name' => 'PT Tab Lain', 'format' => 'TLN']);
        $this->owner = User::factory()->create(['business_entity_id' => $this->home->id]);
        $type = ItemRequestType::create(['name' => 'ATK-tab-relasi']);
        $colleague = User::factory()->create(['business_entity_id' => $this->home->id]);

        $this->ownRequest = ItemRequest::create(['user_id' => $this->owner->id, 'item_request_type_id' => $type->id, 'requested_at' => now()]);
        $this->approvedRequest = ItemRequest::create(['user_id' => $colleague->id, 'item_request_type_id' => $type->id, 'requested_at' => now()]);
        $this->approvedRequest->approvals()->where('approval_type', ItemRequestApprovalType::Manager)->firstOrFail()->markApproved($this->owner);
        $this->report = ProblemReport::create(['user_id' => $this->owner->id, 'reported_at' => now()]);
        $this->heldAsset = Asset::factory()->create([
            'business_entity_id' => $this->home->id,
            'condition_status' => AssetCondition::Transferred,
            'recipient_id' => $this->owner->id,
        ]);

        $this->sent = $this->transfer($this->owner, $colleague);
        $this->received = $this->transfer($colleague, $this->owner);
        $this->unrelated = $this->transfer($colleague, User::factory()->create());
    }

    public function test_super_admin_sees_every_relation_of_the_user(): void
    {
        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
        $this->actingAs($admin);

        $this->tab(AssetTransfersRelationManager::class)
            ->assertCanSeeTableRecords([$this->sent, $this->received])
            ->assertCanNotSeeTableRecords([$this->unrelated])
            ->assertTableColumnStateSet('role', 'Pengirim', $this->sent)
            ->assertTableColumnStateSet('role', 'Penerima', $this->received)
            ->filterTable('role', 'to')
            ->assertCanSeeTableRecords([$this->received])
            ->assertCanNotSeeTableRecords([$this->sent]);

        $this->tab(ItemRequestsRelationManager::class)
            ->assertCanSeeTableRecords([$this->ownRequest])
            ->assertCanNotSeeTableRecords([$this->approvedRequest]);

        $this->tab(ItemRequestApprovalsRelationManager::class)
            ->assertCountTableRecords(1)
            ->assertTableColumnStateSet('request.request_code', $this->approvedRequest->fresh()->request_code, $this->approvedRequest->approvals()->whereNotNull('approved_by_user_id')->first());

        $this->tab(ProblemReportsRelationManager::class)->assertCanSeeTableRecords([$this->report]);
        $this->tab(HeldAssetsRelationManager::class)->assertCanSeeTableRecords([$this->heldAsset]);

        $this->assertSame('2', AssetTransfersRelationManager::getBadge($this->owner, EditUser::class));
        $this->assertSame('1', ItemRequestsRelationManager::getBadge($this->owner, EditUser::class));
    }

    public function test_tabs_hide_records_the_viewer_cannot_see_in_their_own_menus(): void
    {
        // Akses ke menu-menu itu ada, tapi hanya badan usaha lain dan tanpa "lihat semua".
        $viewer = $this->viewer(['view_any_item::request', 'view_any_problem::report', 'view_any_asset', 'view_any_asset::transfer']);
        $this->actingAs($viewer);

        foreach ([AssetTransfersRelationManager::class, ItemRequestsRelationManager::class, ItemRequestApprovalsRelationManager::class, ProblemReportsRelationManager::class, HeldAssetsRelationManager::class] as $tab) {
            $this->assertTrue($tab::canViewForRecord($this->owner, EditUser::class), $tab);
            $this->tab($tab)->assertCountTableRecords(0);
            $this->assertSame('0', $tab::getBadge($this->owner, EditUser::class), $tab);
        }

        // Atasan melihat pengajuan bawahannya, seperti di menu Pengajuan Barang.
        $this->owner->forceFill(['approver_user_id' => $viewer->id])->save();

        $this->tab(ItemRequestsRelationManager::class)->assertCanSeeTableRecords([$this->ownRequest]);
    }

    public function test_tabs_are_hidden_without_access_to_the_related_menu(): void
    {
        $this->actingAs($this->viewer(['view_any_user', 'view_user']));

        foreach ([AssetTransfersRelationManager::class, ItemRequestsRelationManager::class, ItemRequestApprovalsRelationManager::class, ProblemReportsRelationManager::class, HeldAssetsRelationManager::class] as $tab) {
            $this->assertFalse($tab::canViewForRecord($this->owner, EditUser::class), $tab);
        }
    }

    /**
     * @param  class-string  $relationManager
     */
    private function tab(string $relationManager): mixed
    {
        return Livewire::test($relationManager, ['ownerRecord' => $this->owner, 'pageClass' => EditUser::class]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function viewer(array $permissions): User
    {
        $viewer = User::factory()->create(['business_entity_id' => $this->other->id, 'access_all_business_entities' => false]);
        $role = Role::findOrCreate('uji_tab_relasi_user', 'web');
        $role->syncPermissions(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $permissions));
        $viewer->assignRole($role);

        return $viewer;
    }

    private function transfer(User $from, User $to): AssetTransfer
    {
        return AssetTransfer::create([
            'business_entity_id' => $this->home->id,
            'document_type' => AssetTransferDocumentType::SerahTerima,
            'letter_number' => '__TRL/'.uniqid().'__',
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'transfer_date' => now()->toDateString(),
        ]);
    }
}
