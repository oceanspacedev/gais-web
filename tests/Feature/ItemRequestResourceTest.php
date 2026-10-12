<?php

namespace Tests\Feature;

use App\Enums\ItemRequestExecutorDecision;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Resources\ItemRequestResource\Pages\CreateItemRequest;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Filament\Resources\ItemRequestResource\Pages\ViewItemRequest;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\ItemRequestType;
use App\Models\Product;
use App\Models\User;
use App\Services\ItemRequestWorkflow;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ItemRequestResourceTest extends TestCase
{
    use DatabaseTransactions;

    private ItemRequestType $atk;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->atk = ItemRequestType::create(['name' => 'ATK-ui', 'requires_products' => true]);
        $this->product = Product::create(['name' => 'Kertas-ui', 'price' => 50000]);
    }

    public function test_requester_creates_a_request_from_the_panel_and_sees_only_their_own(): void
    {
        $requester = $this->userWithRole('requester');
        $stranger = $this->userWithRole('requester');
        $foreign = $this->request($stranger);

        $this->actingAs($requester);

        Livewire::test(CreateItemRequest::class)
            ->fillForm([
                'item_request_type_id' => $this->atk->id,
                'items' => [
                    ['product_id' => $this->product->id, 'qty_request' => 2, 'description' => 'A4'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = ItemRequest::query()->where('user_id', $requester->id)->firstOrFail();
        $this->assertSame(2.0, (float) $created->details()->first()->qty_request);
        $this->assertSame($this->atk->id, $created->item_request_type_id);

        Livewire::test(ListItemRequests::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$created])
            ->assertCanNotSeeTableRecords([$foreign]);

        $this->assertSame([$created->id], ItemRequestResource::getEloquentQuery()->pluck('id')->all());
    }

    public function test_executor_in_scope_can_process_and_requester_can_then_confirm(): void
    {
        $division = Division::create(['name' => 'HCM-ui']);
        ItemRequestScope::create(['division_id' => $division->id]);
        $executor = $this->userWithRole('executor', ['division_id' => $division->id]);
        $requester = $this->userWithRole('requester');
        $request = $this->request($requester);

        $this->actingAs($executor);
        Livewire::test(ListItemRequests::class)->assertCanSeeTableRecords([$request]);
        Livewire::test(ViewItemRequest::class, ['record' => $request->getRouteKey()])
            ->assertOk()
            ->assertActionVisible('process')
            ->assertActionHidden('confirm')
            ->callAction('process', ['decision' => ItemRequestExecutorDecision::Closed->value, 'notes' => 'beres'])
            ->assertNotified();

        $this->assertSame(4, $request->refresh()->client_status->value);

        $this->actingAs($requester);
        Livewire::test(ViewItemRequest::class, ['record' => $request->getRouteKey()])
            ->assertOk()
            ->assertActionHidden('process')
            ->assertActionVisible('confirm')
            ->callAction('confirm', ['accepted' => '1', 'user_notes' => 'ok'])
            ->assertNotified();

        $this->assertSame(1, $request->refresh()->client_status->value);
    }

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }

    private function request(User $requester): ItemRequest
    {
        return app(ItemRequestWorkflow::class)->create($requester, [
            'item_request_type_id' => $this->atk->id,
            'items' => [['product_id' => $this->product->id, 'qty_request' => 1]],
        ]);
    }
}
