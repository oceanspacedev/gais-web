<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\IndexedPermissionRegistrar;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PermissionCheckCacheTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        app(PermissionRegistrar::class)->initializeCache();
    }

    public function test_the_indexed_registrar_replaces_spatie_registrar(): void
    {
        $this->assertInstanceOf(IndexedPermissionRegistrar::class, app(PermissionRegistrar::class));
    }

    public function test_remembered_checks_follow_role_changes_in_the_same_request(): void
    {
        $permission = Permission::findOrCreate('__perm_cache_probe__', 'web');
        $role = Role::findOrCreate('__perm_cache_role__', 'web');
        $user = User::factory()->create();

        $this->assertFalse($user->can('__perm_cache_probe__'));

        $role->givePermissionTo($permission);
        $user->assignRole($role);
        $this->assertTrue($user->can('__perm_cache_probe__'));

        $user->removeRole($role);
        $this->assertFalse($user->can('__perm_cache_probe__'));
    }

    public function test_remembered_checks_follow_direct_permission_changes(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->checkPermissionTo('__perm_cache_direct__'));

        $permission = Permission::findOrCreate('__perm_cache_direct__', 'web');
        $this->assertFalse($user->checkPermissionTo('__perm_cache_direct__'));

        $user->givePermissionTo($permission);
        $this->assertTrue($user->checkPermissionTo('__perm_cache_direct__'));

        $user->revokePermissionTo($permission);
        $this->assertFalse($user->checkPermissionTo('__perm_cache_direct__'));
    }

    public function test_remembered_checks_follow_permissions_given_to_a_role(): void
    {
        $permission = Permission::findOrCreate('__perm_cache_role_perm__', 'web');
        $role = Role::findOrCreate('__perm_cache_role_2__', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->assertFalse($user->can('__perm_cache_role_perm__'));

        $role->givePermissionTo($permission);
        $this->assertTrue($user->can('__perm_cache_role_perm__'));
    }
}
