<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\BusinessEntity;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use STS\FilamentImpersonate\Actions\Impersonate;
use STS\FilamentImpersonate\Facades\Impersonation;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Impersonation is limited to the impersonator's business entities, so
     * every user in these tests shares one entity.
     */
    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        config(['permission.cache.store' => 'array']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (['Impersonate:User', 'ViewAny:User', 'View:User', 'Update:User'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->entity = BusinessEntity::create(['name' => '__impersonate_entity__']);
    }

    public function test_shield_exposes_impersonate_as_a_user_resource_permission(): void
    {
        $permissions = FilamentShield::getResourcePolicyActionsWithPermissions(UserResource::class);

        $this->assertSame('Impersonate:User', $permissions['impersonate'] ?? null);
        $this->assertContains('impersonate', config('filament-shield.policies.single_parameter_methods'));
    }

    public function test_impersonate_labels_are_translated_to_indonesian(): void
    {
        app()->setLocale('id');

        $this->assertSame('Login Sebagai', FilamentShield::getAffixLabel('impersonate'));
        $this->assertSame('Login Sebagai', __('filament-impersonate::action.label'));
        $this->assertSame('Anda sedang login sebagai', __('filament-impersonate::banner.impersonating'));
        $this->assertSame('Kembali ke Akun Saya', __('filament-impersonate::banner.leave'));

        app()->setLocale('en');

        $this->assertSame('Impersonate', FilamentShield::getAffixLabel('impersonate'));
    }

    public function test_impersonate_requires_the_shield_permission(): void
    {
        $withoutPermission = $this->userWithRole('__impersonate_none__', ['ViewAny:User']);
        $withPermission = $this->userWithRole('__impersonate_allowed__', ['ViewAny:User', 'Impersonate:User']);

        $this->assertFalse($withoutPermission->canImpersonate());
        $this->assertTrue($withPermission->canImpersonate());
    }

    public function test_only_panel_users_can_be_impersonated(): void
    {
        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['Impersonate:User']));

        $this->assertTrue($this->userWithRole('__impersonate_target__')->canBeImpersonated());
        $this->assertFalse(User::factory()->create(['business_entity_id' => $this->entity->id])->canBeImpersonated());
    }

    public function test_users_outside_the_impersonators_business_entities_cannot_be_impersonated(): void
    {
        $otherEntity = BusinessEntity::create(['name' => '__impersonate_other_entity__']);
        $target = $this->userWithRole('__impersonate_target__');
        $target->update(['business_entity_id' => $otherEntity->id]);

        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['Impersonate:User']));
        $this->assertFalse($target->fresh()->canBeImpersonated());

        // Super admin menjangkau semua badan usaha lewat flag, bukan lewat rolenya.
        $superAdmin = $this->userWithRole(config('filament-shield.super_admin.name'));
        $this->actingAs($superAdmin);
        $this->assertFalse($target->fresh()->canBeImpersonated());

        $superAdmin->update(['access_all_business_entities' => true]);
        $this->actingAs($superAdmin->fresh());
        $this->assertTrue($target->fresh()->canBeImpersonated());
    }

    public function test_super_admin_cannot_be_impersonated(): void
    {
        $superAdminRole = config('filament-shield.super_admin.name');
        $target = $this->userWithRole($superAdminRole);

        $this->actingAs($this->userWithRole('__impersonate_allowed__', ['Impersonate:User']));
        $this->assertFalse($target->canBeImpersonated());

        $superAdmin = $this->userWithRole($superAdminRole);
        $superAdmin->update(['access_all_business_entities' => true]);
        $this->actingAs($superAdmin->fresh());
        $this->assertFalse($target->canBeImpersonated());
    }

    public function test_table_action_is_hidden_without_permission(): void
    {
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($this->userWithRole('__impersonate_none__', ['ViewAny:User']));

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('impersonate')->table($target));
    }

    public function test_table_action_impersonates_the_selected_user(): void
    {
        $impersonator = $this->userWithRole('__impersonate_allowed__', ['ViewAny:User', 'Impersonate:User']);
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($impersonator);

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('impersonate')->table($target))
            ->assertActionHidden(TestAction::make('impersonate')->table($impersonator))
            ->callAction(TestAction::make('impersonate')->table($target))
            ->assertRedirect(Filament::getPanel('admin')->getUrl());

        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertSame($impersonator->getKey(), Impersonation::getImpersonatorId());
    }

    public function test_revoking_the_permission_from_the_role_hides_and_blocks_the_action(): void
    {
        $impersonator = $this->userWithRole('__impersonate_revoked__', ['ViewAny:User', 'Impersonate:User']);
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($impersonator);

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('impersonate')->table($target));

        Role::findByName('__impersonate_revoked__', 'web')->revokePermissionTo('Impersonate:User');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $impersonator = $impersonator->fresh();
        $this->actingAs($impersonator);

        $this->assertFalse($impersonator->canImpersonate());

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('impersonate')->table($target));

        // Tombol tersembunyi; aksi plugin juga menolak bila dipanggil langsung.
        $this->assertFalse((new Impersonate('impersonate'))->impersonate($target));
        $this->assertFalse(Impersonation::isImpersonating());
        $this->assertSame($impersonator->getKey(), Filament::auth()->id());
    }

    public function test_impersonator_can_leave_and_both_steps_are_logged(): void
    {
        $impersonator = $this->userWithRole('__impersonate_allowed__', ['ViewAny:User', 'Impersonate:User']);
        $target = $this->userWithRole('__impersonate_target__');

        Log::spy();
        $this->actingAs($impersonator);

        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('impersonate')->table($target));

        $this->assertSame($target->getKey(), Filament::auth()->id());

        $this->assertTrue(Impersonation::leave());

        $this->assertFalse(Impersonation::isImpersonating());
        $this->assertSame($impersonator->getKey(), Filament::auth()->id());

        Log::shouldHaveReceived('info')->with('Impersonation started.', \Mockery::on(
            fn (array $context): bool => $context['impersonator_id'] === $impersonator->getKey() && $context['impersonated_id'] === $target->getKey()
        ))->once();
        Log::shouldHaveReceived('info')->with('Impersonation ended.', \Mockery::on(
            fn (array $context): bool => $context['impersonator_id'] === $impersonator->getKey() && $context['impersonated_id'] === $target->getKey()
        ))->once();
    }

    public function test_edit_page_shows_impersonate_header_action(): void
    {
        $target = $this->userWithRole('__impersonate_target__');

        $this->actingAs($this->userWithRole('__impersonate_allowed__', [
            'ViewAny:User',
            'View:User',
            'Update:User',
            'Impersonate:User',
        ]));

        Livewire::test(EditUser::class, ['record' => $target->getRouteKey()])
            ->assertActionVisible('impersonate');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithRole(string $roleName, array $permissions = []): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $role->syncPermissions($permissions);

        $user = User::factory()->create(['business_entity_id' => $this->entity->id]);
        $user->assignRole($role);

        return $user->fresh();
    }
}
