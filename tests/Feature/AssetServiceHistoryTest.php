<?php

namespace Tests\Feature;

use App\Enums\AssetServiceStatus;
use App\Filament\Resources\AssetResource;
use App\Filament\Resources\AssetResource\Pages\ViewAsset;
use App\Filament\Resources\AssetResource\RelationManagers\AssetServicesRelationManager;
use App\Filament\Resources\AssetServiceResource;
use App\Filament\Resources\AssetServiceResource\Pages\CreateAssetService;
use App\Models\Asset;
use App\Models\AssetService;
use App\Models\BusinessEntity;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Riwayat Servis di halaman detail aset: isinya mengikuti aturan lihat menu
 * Servis Aset, dan servis aset lain atau aset luar tidak ikut tampil.
 */
class AssetServiceHistoryTest extends TestCase
{
    use DatabaseTransactions;

    private Asset $asset;

    private User $technician;

    private AssetService $older;

    private AssetService $newer;

    private AssetService $otherAsset;

    private AssetService $external;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $entity = BusinessEntity::create(['name' => 'PT Riwayat Servis', 'format' => 'RWS']);
        $this->asset = Asset::factory()->create(['business_entity_id' => $entity->id]);
        $otherAsset = Asset::factory()->create(['business_entity_id' => $entity->id]);
        $this->technician = User::factory()->create(['business_entity_id' => $entity->id]);
        $creator = User::factory()->create(['business_entity_id' => $entity->id]);

        $this->older = $this->service($this->asset, $creator, '2026-09-01', 'Baterai drop', ['total_cost' => 350000]);
        $this->newer = $this->service($this->asset, $creator, '2026-11-01', 'Layar bergaris', [
            'serviced_by_user_id' => $this->technician->id,
            'status' => AssetServiceStatus::Completed->value,
        ]);
        $this->otherAsset = $this->service($otherAsset, $creator, '2026-10-01', 'Servis aset lain');
        $this->external = $this->service(null, $creator, '2026-10-15', 'Servis aset luar', ['external_asset_name' => 'Printer Toko']);
    }

    public function test_admin_sees_every_service_of_the_asset_newest_first_with_links(): void
    {
        $this->actingAs($this->admin());

        $this->assertTrue(AssetServicesRelationManager::canViewForRecord($this->asset, ViewAsset::class));
        $this->assertSame('2', AssetServicesRelationManager::getBadge($this->asset, ViewAsset::class));

        $this->tab()
            ->assertCanSeeTableRecords([$this->newer, $this->older], inOrder: true)
            ->assertCanNotSeeTableRecords([$this->otherAsset, $this->external])
            ->assertTableColumnStateSet('total_cost', 'Rp 350.000', $this->older)
            ->assertTableColumnStateSet('issue_description', 'Layar bergaris', $this->newer)
            ->assertTableColumnFormattedStateSet('status', AssetServiceStatus::Completed->label(), $this->newer)
            ->assertSee(AssetServiceResource::getUrl('view', ['record' => $this->newer]), escape: false);
    }

    public function test_viewer_without_view_all_only_sees_services_they_are_involved_in(): void
    {
        $this->actingAs($this->viewer($this->technician, ['ViewAny:Asset', 'View:Asset', 'ViewAny:AssetService']));

        $this->assertTrue(AssetServicesRelationManager::canViewForRecord($this->asset, ViewAsset::class));
        $this->assertSame('1', AssetServicesRelationManager::getBadge($this->asset, ViewAsset::class));

        // Tanpa izin lihat detail servis, barisnya tidak bertaut ke halaman servis.
        $this->tab()
            ->assertCanSeeTableRecords([$this->newer])
            ->assertCanNotSeeTableRecords([$this->older, $this->otherAsset, $this->external])
            ->assertDontSee(AssetServiceResource::getUrl('view', ['record' => $this->newer]), escape: false);
    }

    public function test_tab_is_hidden_without_access_to_asset_services(): void
    {
        $viewer = $this->viewer(User::factory()->create(), ['ViewAny:Asset', 'View:Asset']);
        $this->actingAs($viewer);

        $this->assertFalse(AssetServicesRelationManager::canViewForRecord($this->asset, ViewAsset::class));
    }

    public function test_service_created_from_the_service_form_shows_in_the_asset_history(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CreateAssetService::class)
            ->fillForm([
                'asset_source' => 'registered',
                'asset_id' => $this->asset->id,
                'service_date' => '2026-11-16',
                'provider_type' => 'internal',
                'serviced_by_user_id' => $admin->id,
                'issue_description' => 'Engsel patah dari form',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = AssetService::where('issue_description', 'Engsel patah dari form')->firstOrFail();

        $this->tab()->assertCanSeeTableRecords([$created, $this->newer, $this->older], inOrder: true);

        $this->get(AssetResource::getUrl('view', ['record' => $this->asset]))
            ->assertSuccessful()
            ->assertSeeText('Riwayat Servis');
    }

    private function tab(): mixed
    {
        return Livewire::test(AssetServicesRelationManager::class, ['ownerRecord' => $this->asset, 'pageClass' => ViewAsset::class]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));

        return $admin;
    }

    /**
     * @param  list<string>  $permissions
     */
    private function viewer(User $user, array $permissions): User
    {
        $role = Role::findOrCreate('uji_riwayat_servis_aset', 'web');
        $role->syncPermissions(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $permissions));
        $user->assignRole($role);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function service(?Asset $asset, User $creator, string $date, string $issue, array $attributes = []): AssetService
    {
        return AssetService::create([
            'asset_id' => $asset?->id,
            'provider_type' => 'internal',
            'service_date' => $date,
            'issue_description' => $issue,
            'status' => AssetServiceStatus::Pending->value,
            'created_by' => $creator->id,
            ...$attributes,
        ]);
    }
}
