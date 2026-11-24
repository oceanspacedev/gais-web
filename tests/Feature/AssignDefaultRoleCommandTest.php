<?php

namespace Tests\Feature;

use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignDefaultRoleCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_users_without_a_role_get_requester_and_others_are_untouched(): void
    {
        $plain = User::factory()->create();
        $executor = User::factory()->create();
        $executor->assignRole(Role::findOrCreate('executor', 'web'));

        $this->artisan('users:assign-default-role', ['--dry-run' => true])->assertSuccessful();
        $this->assertFalse($plain->fresh()->roles()->exists());

        $this->artisan('users:assign-default-role')->assertSuccessful();

        $this->assertSame(['requester'], $plain->fresh()->getRoleNames()->all());
        $this->assertSame(['executor'], $executor->fresh()->getRoleNames()->all());
        $this->assertFalse(User::query()->whereDoesntHave('roles')->exists());

        // Dijalankan ulang tidak menggandakan apa pun.
        $this->artisan('users:assign-default-role')->assertSuccessful();
        $this->assertCount(1, $plain->fresh()->roles);
    }

    public function test_unknown_role_fails(): void
    {
        $this->artisan('users:assign-default-role', ['--role' => 'tidak-ada-role-ini'])->assertFailed();
    }

    public function test_requester_without_division_still_chooses_a_division_before_creating(): void
    {
        $employee = User::factory()->create();
        $employee->assignRole('requester');
        $employee->refresh();

        $this->assertTrue($employee->can('create', ItemRequest::class));
        $this->assertTrue($employee->needsToChooseDivision());

        $this->actingAs($employee);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(ItemRequestResource::getUrl('create'))->assertRedirect(ItemRequestResource::getUrl('index'));

        Livewire::test(ListItemRequests::class)
            ->assertSet('defaultAction', 'chooseDivision')
            ->assertActionHidden('create');

        $employee->update(['division_id' => Division::create(['name' => 'CS-uji-role-default'])->id]);

        $this->assertFalse($employee->fresh()->needsToChooseDivision());
        $this->get(ItemRequestResource::getUrl('create'))->assertOk();
    }

    public function test_processors_without_division_are_not_asked_to_choose_one(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));

        $this->assertFalse($superAdmin->fresh()->needsToChooseDivision());

        $generalAffair = User::factory()->create();
        $generalAffair->givePermissionTo('ViewAll:ItemRequest');

        $this->assertFalse($generalAffair->fresh()->needsToChooseDivision());
    }
}
