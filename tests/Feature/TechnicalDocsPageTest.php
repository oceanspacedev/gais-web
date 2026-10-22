<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TechnicalDocsPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_with_permission_sees_the_rendered_docs(): void
    {
        $role = Role::firstOrCreate(['name' => 'technical_docs_reader', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::findOrCreate('View:TechnicalDocs', 'web'));
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin/dokumentasi-teknis')
            ->assertOk()
            ->assertSee('Dokumentasi Teknis')
            ->assertSee('id="td-tech-stack"', false)
            ->assertSee('id="td-database"', false)
            ->assertSee('Bab 9 dari 9')
            ->assertSee('class="td-acc"', false)
            ->assertSee('<table>', false)
            ->assertSee('class="td-erd"', false)
            ->assertSee('Isi dokumentasi')
            ->assertDontSee('<!-- erd -->', false)
            ->assertDontSee('<!-- desc:', false);
    }

    public function test_super_admin_role_holds_the_permission(): void
    {
        $role = Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->markTestSkipped('Role super_admin belum ada di database ini.');
        }

        $this->assertTrue($role->hasPermissionTo('View:TechnicalDocs'));
    }

    public function test_panel_user_without_permission_is_forbidden(): void
    {
        $role = Role::firstOrCreate(['name' => 'requester', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)
            ->get('/admin/dokumentasi-teknis')
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/dokumentasi-teknis')->assertRedirect();
    }
}
