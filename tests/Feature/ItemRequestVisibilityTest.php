<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ItemRequestVisibilityTest extends TestCase
{
    use DatabaseTransactions;

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
