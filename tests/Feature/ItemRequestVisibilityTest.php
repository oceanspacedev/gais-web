<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\BusinessEntity;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AssertsVisibilityContract;
use Tests\TestCase;

class ItemRequestVisibilityTest extends TestCase
{
    use AssertsVisibilityContract, DatabaseTransactions;

    private ItemRequestType $atk;

    private ItemRequestType $asset;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->atk = ItemRequestType::create(['name' => 'ATK-test', 'requires_products' => true]);
        $this->asset = ItemRequestType::create(['name' => 'ASSET-test', 'requires_attachment' => true, 'requires_accounting_approval' => true]);
    }

    public function test_new_request_gets_a_code_and_four_approval_rows(): void
    {
        $request = $this->request(User::factory()->create(), $this->atk);

        $this->assertSame('REQ'.$request->id, $request->fresh()->request_code);
        $this->assertSame(
            ['ACCOUNTING', 'MANAGER', 'EXECUTOR', 'ENDUSER'],
            $request->approvals()->orderBy('id')->pluck('approval_type')->map(fn ($type) => $type->value)->all(),
        );
    }

    public function test_requester_only_sees_own_requests_and_approver_sees_subordinates(): void
    {
        $manager = User::factory()->create();
        $requester = User::factory()->create(['approver_user_id' => $manager->id]);
        $stranger = User::factory()->create();

        $mine = $this->request($requester, $this->atk);
        $theirs = $this->request($stranger, $this->atk);

        $this->assertSame([$mine->id], $this->visibleIds($requester));
        $this->assertSame([$mine->id], $this->visibleIds($manager));
        $this->assertSame([$theirs->id], $this->visibleIds($stranger));
    }

    public function test_accounting_sees_types_that_need_accounting_approval(): void
    {
        $accounting = User::factory()->create();
        $accounting->givePermissionTo($this->permission('approve_accounting_item::request'));

        $assetRequest = $this->request(User::factory()->create(), $this->asset);
        $this->request(User::factory()->create(), $this->atk);

        $this->assertSame([$assetRequest->id], $this->visibleIds($accounting));
    }

    public function test_executor_scope_limits_by_type_and_requester_area(): void
    {
        $depo = Area::create(['name' => 'DEPO-test']);
        $ho = Area::create(['name' => 'HO-test']);
        $warehouse = Division::create(['name' => 'WHM-test', 'area_id' => $ho->id]);
        $depoDivision = Division::create(['name' => 'DEPO-DIV-test', 'area_id' => $depo->id]);
        $hoDivision = Division::create(['name' => 'HO-DIV-test', 'area_id' => $ho->id]);

        $executor = User::factory()->create(['division_id' => $warehouse->id]);
        $executor->givePermissionTo($this->permission('process_item::request'));
        ItemRequestScope::create(['division_id' => $warehouse->id, 'item_request_type_id' => $this->atk->id, 'area_id' => $depo->id]);

        $depoAtk = $this->request(User::factory()->create(['division_id' => $depoDivision->id]), $this->atk);
        $this->request(User::factory()->create(['division_id' => $depoDivision->id]), $this->asset);
        $this->request(User::factory()->create(['division_id' => $hoDivision->id]), $this->atk);

        $this->assertSame([$depoAtk->id], $this->visibleIds($executor));
    }

    public function test_scope_without_type_or_area_and_view_all_see_everything(): void
    {
        $hcm = Division::create(['name' => 'HCM-test']);
        $executor = User::factory()->create(['division_id' => $hcm->id]);
        $executor->givePermissionTo($this->permission('process_item::request'));
        ItemRequestScope::create(['division_id' => $hcm->id]);

        $admin = User::factory()->create();
        $admin->givePermissionTo($this->permission('view_all_item::request'));

        $a = $this->request(User::factory()->create(), $this->atk);
        $b = $this->request(User::factory()->create(), $this->asset);

        $this->assertSame([$a->id, $b->id], $this->visibleIds($executor));
        $this->assertSame([$a->id, $b->id], $this->visibleIds($admin));
    }

    public function test_policy_check_agrees_with_the_visibility_query(): void
    {
        $depo = Area::create(['name' => 'DEPO-test']);
        $ho = Area::create(['name' => 'HO-test']);
        $warehouse = Division::create(['name' => 'WHM-test', 'area_id' => $ho->id]);
        $audit = Division::create(['name' => 'AUDIT-test', 'area_id' => $ho->id]);
        $purchasing = Division::create(['name' => 'PURCH-test', 'area_id' => $ho->id]);
        $hcm = Division::create(['name' => 'HCM-test']);
        $depoDivision = Division::create(['name' => 'DEPO-DIV-test', 'area_id' => $depo->id]);
        $hoDivision = Division::create(['name' => 'HO-DIV-test', 'area_id' => $ho->id]);
        $closedDivision = Division::create(['name' => 'CLOSED-DIV-test', 'area_id' => $depo->id]);
        $retiredType = ItemRequestType::create(['name' => 'RETIRED-test', 'requires_accounting_approval' => true]);

        ItemRequestScope::create(['division_id' => $warehouse->id, 'item_request_type_id' => $this->atk->id, 'area_id' => $depo->id]);
        ItemRequestScope::create(['division_id' => $audit->id, 'item_request_type_id' => $this->asset->id]);
        ItemRequestScope::create(['division_id' => $purchasing->id, 'area_id' => $ho->id]);
        ItemRequestScope::create(['division_id' => $hcm->id]);

        $manager = User::factory()->create();
        $requester = User::factory()->create(['approver_user_id' => $manager->id, 'division_id' => $depoDivision->id]);
        $hoUser = User::factory()->create(['division_id' => $hoDivision->id]);
        $closedUser = User::factory()->create(['division_id' => $closedDivision->id]);

        // Badan usaha membatasi semua grant, termasuk pemohon dan atasannya.
        $otherEntity = BusinessEntity::create(['name' => '__irv_other__']);
        $restrictedRequester = User::factory()->create(['approver_user_id' => $manager->id, 'business_entity_id' => BusinessEntity::create(['name' => '__irv_home__'])->id]);

        $viewers = [
            'requester' => $requester,
            'restricted requester' => $restrictedRequester,
            'manager' => $manager,
            'stranger' => User::factory()->create(),
            'accounting' => $this->userWith(['approve_accounting_item::request']),
            'executor type+area' => $this->userWith(['process_item::request'], $warehouse),
            'audit type only' => $this->userWith(['audit_item::request'], $audit),
            'manager area only' => $this->userWith(['approve_manager_item::request'], $purchasing),
            'executor everything' => $this->userWith(['process_item::request'], $hcm),
            'scoped division without permission' => User::factory()->create(['division_id' => $hcm->id]),
            'executor without division' => $this->userWith(['process_item::request']),
            'view all' => $this->userWith(['view_all_item::request']),
        ];

        $requests = [
            $this->request($requester, $this->atk),
            $this->request($requester, $this->asset),
            $this->request($hoUser, $this->atk),
            $this->request($hoUser, $this->asset),
            $this->request($closedUser, $this->atk),
            $this->request($hoUser, $retiredType),
            ItemRequest::create(['user_id' => null, 'item_request_type_id' => $this->asset->id, 'requested_at' => now()]),
            ItemRequest::create(['user_id' => null, 'item_request_type_id' => $this->atk->id, 'requested_at' => now()]),
            ItemRequest::create(['user_id' => $restrictedRequester->id, 'item_request_type_id' => $this->atk->id, 'requested_at' => now(), 'business_entity_id' => $otherEntity->id]),
            ItemRequest::create(['user_id' => $restrictedRequester->id, 'item_request_type_id' => $this->atk->id, 'requested_at' => now(), 'business_entity_id' => $restrictedRequester->business_entity_id]),
        ];
        $trashed = $this->request($requester, $this->atk);
        $trashed->delete();
        $closedDivision->delete();
        $retiredType->delete();

        $ids = [...array_map(fn (ItemRequest $request): int => $request->id, $requests), $trashed->id];

        $this->assertSame([$requests[9]->id], ItemRequest::query()->whereKey($ids)->where('user_id', $restrictedRequester->id)->visibleTo($restrictedRequester)->pluck('id')->all(), 'Pengajuan sendiri di badan usaha lain tetap tersembunyi.');

        // Seperti di tabel (relasi dimuat) dan seperti halaman view/edit (tanpa relasi).
        $this->assertQueryAndPolicyAgree(ItemRequest::class, $viewers, $ids, [
            'eager' => ['user.division', 'type'],
            'lazy' => [],
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, ?Division $division = null): User
    {
        $user = User::factory()->create(['division_id' => $division?->id]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo($this->permission($permission));
        }

        return $user;
    }

    private function request(User $user, ItemRequestType $type): ItemRequest
    {
        return ItemRequest::create([
            'user_id' => $user->id,
            'item_request_type_id' => $type->id,
            'requested_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function visibleIds(User $user): array
    {
        return ItemRequest::query()
            ->whereIn('item_request_type_id', [$this->atk->id, $this->asset->id])
            ->visibleTo($user)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    private function permission(string $name): Permission
    {
        return Permission::findOrCreate($name, 'web');
    }
}
