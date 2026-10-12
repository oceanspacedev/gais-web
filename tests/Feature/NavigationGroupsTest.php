<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetLocationResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NavigationGroupsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menus_are_grouped_per_module_in_order(): void
    {
        $navigation = $this->navigationFor($this->superAdmin());

        $this->assertSame(
            ['', 'Aset', 'Master Aset', 'OB / Kebersihan', 'Kendaraan', 'Pekerjaan Vendor', 'Organisasi', 'Pengaturan'],
            collect($navigation)->map(fn (NavigationGroup $group): string => (string) $group->getLabel())->values()->all(),
        );

        $this->assertSame(
            ['OB Checksheets', 'Ruangan OB', 'Shift OB', AssetLocationResource::getNavigationLabel()],
            $this->labels($navigation, 'OB / Kebersihan'),
        );
        $this->assertContains(AssetLocationResource::getNavigationLabel(), $this->labels($navigation, 'Master Aset'));
        $this->assertContains(AssetLocationResource::getNavigationLabel(), $this->labels($navigation, 'Organisasi'));
    }

    public function test_settings_group_is_collapsed_by_default(): void
    {
        $group = collect(Filament::getPanel('admin')->getNavigationGroups())
            ->first(fn (mixed $group): bool => $group instanceof NavigationGroup && $group->getLabel() === 'Pengaturan');

        $this->assertTrue($group->isCollapsed());
    }

    public function test_office_boy_only_sees_the_ob_group(): void
    {
        $officeBoy = User::factory()->create();
        $officeBoy->givePermissionTo($this->permissions(['view_any_ob::checksheet', 'view_ob::checksheet', 'create_ob::checksheet']));

        $navigation = $this->navigationFor($officeBoy);

        $this->assertSame(['OB Checksheets'], $this->labels($navigation, 'OB / Kebersihan'));
        $this->assertNotContains('Aset', collect($navigation)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all());
    }

    public function test_dashboard_renders_with_the_grouped_sidebar(): void
    {
        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('OB / Kebersihan')
            ->assertSee('sidebar-default-collapsed-groups', false);
    }

    /**
     * @return array<int, NavigationGroup>
     */
    private function navigationFor(User $user): array
    {
        $this->actingAs($user);
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);
        $panel->boot();

        return Filament::getNavigation();
    }

    /**
     * @param  array<int, NavigationGroup>  $navigation
     * @return list<string>
     */
    private function labels(array $navigation, string $group): array
    {
        $found = collect($navigation)->first(fn (NavigationGroup $item): bool => $item->getLabel() === $group);

        return collect($found?->getItems() ?? [])
            ->map(fn (NavigationItem $item): string => (string) $item->getLabel())
            ->values()
            ->all();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate(config('filament-shield.super_admin.name', 'super_admin'), 'web'));

        return $user;
    }

    /**
     * @param  list<string>  $names
     * @return list<Permission>
     */
    private function permissions(array $names): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), $names);
    }
}
