<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Support\EnsureAssetExportPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnsureAssetExportPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
    }

    public function test_grants_export_asset_to_admin_and_general_affair_users(): void
    {
        Permission::findOrCreate('Export:Asset', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('general_affair', 'web');
        Role::findOrCreate('Security', 'web');

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $generalAffair = User::factory()->create();
        $generalAffair->assignRole('general_affair');
        $security = User::factory()->create();
        $security->assignRole('Security');

        EnsureAssetExportPermissions::run();

        $this->assertTrue($admin->fresh()->can('Export:Asset'));
        $this->assertTrue($admin->fresh()->can('export', Asset::class));
        $this->assertTrue($generalAffair->fresh()->can('Export:Asset'));
        $this->assertTrue($generalAffair->fresh()->can('export', Asset::class));
        $this->assertFalse($security->fresh()->can('Export:Asset'));
    }

    public function test_skips_missing_roles_and_is_idempotent(): void
    {
        EnsureAssetExportPermissions::run(['__missing_asset_export_role__']);
        EnsureAssetExportPermissions::run(['__missing_asset_export_role__']);

        $this->assertTrue(Permission::where('name', 'Export:Asset')->where('guard_name', 'web')->exists());
        $this->assertFalse(Role::where('name', '__missing_asset_export_role__')->exists());
    }
}
