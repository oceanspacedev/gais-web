<?php

namespace Tests\Feature;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestExecutorDecision;
use App\Exceptions\ItemRequestException;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\Product;
use App\Models\User;
use App\Services\ItemRequestWorkflow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ItemRequestWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private ItemRequestWorkflow $workflow;

    private ItemRequestType $atk;

    private ItemRequestType $asset;

    private Product $product;

    private User $requester;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        config(['item-request.max_open_per_type' => 2]);

        $this->workflow = app(ItemRequestWorkflow::class);
        $this->atk = ItemRequestType::create(['name' => 'ATK-wf', 'requires_products' => true]);
        $this->asset = ItemRequestType::create(['name' => 'ASSET-wf', 'requires_attachment' => true, 'requires_accounting_approval' => true]);
        $this->product = Product::create(['name' => 'Pulpen-wf', 'price' => 2500]);
        $this->requester = User::factory()->create();
        $this->staff = User::factory()->create();
    }

    public function test_type_rules_are_enforced_on_create(): void
    {
        try {
            $this->workflow->create($this->requester, ['item_request_type_id' => $this->atk->id, 'items' => []]);
            $this->fail('ATK tanpa barang seharusnya ditolak.');
        } catch (ItemRequestException $exception) {
            $this->assertStringContainsString('pilih barang', $exception->getMessage());
        }

        try {
            $this->workflow->create($this->requester, ['item_request_type_id' => $this->asset->id]);
            $this->fail('ASSET tanpa lampiran seharusnya ditolak.');
        } catch (ItemRequestException $exception) {
            $this->assertStringContainsString('persetujuan atasan', $exception->getMessage());
        }

        $request = $this->workflow->create($this->requester, [
            'item_request_type_id' => $this->atk->id,
            'items' => [['product_id' => $this->product->id, 'qty_request' => 3, 'description' => 'biru']],
        ]);

        $this->assertSame('REQ'.$request->id, $request->request_code);
        $this->assertSame($this->requester->id, $request->user_id);
        $this->assertCount(1, $request->details);
        $this->assertCount(4, $request->approvals);
        $this->assertSame(ItemRequestClientStatus::Waiting, $request->client_status);
    }

    public function test_at_most_two_open_requests_per_type(): void
    {
        $first = $this->createAtk();
        $this->createAtk();

        $available = $this->workflow->availableTypes($this->requester)->pluck('id')->all();
        $this->assertContains($this->asset->id, $available);
        $this->assertNotContains($this->atk->id, $available);

        try {
            $this->createAtk();
            $this->fail('Pengajuan ketiga seharusnya ditolak.');
        } catch (ItemRequestException $exception) {
            $this->assertStringContainsString('maksimal 2', $exception->getMessage());
        }

        $this->workflow->process($first, $this->staff, ItemRequestExecutorDecision::Closed, 'selesai');
        $this->workflow->confirm($first, $this->requester, true, 'terima kasih');

        $this->assertSame(1, $this->workflow->openCountByType($this->requester, $this->atk->id));
        $this->assertInstanceOf(ItemRequest::class, $this->createAtk());
    }

    public function test_stages_accounting_manager_executor_and_confirmation(): void
    {
        $request = $this->workflow->create($this->requester, [
            'item_request_type_id' => $this->asset->id,
            'request_file' => 'Request_File/x.pdf',
            'items' => [['product_id' => $this->product->id, 'qty_request' => 4]],
        ]);

        $this->assertSame(0, $this->workflow->scopeAwaitingExecutor(ItemRequest::query()->whereKey($request->id))->count(), 'Tipe dengan tahap Accounting belum siap diproses.');

        $this->workflow->approveAccounting($request, $this->staff, true, 'PO-77');
        $request->refresh();
        $this->assertTrue($request->po_status);
        $this->assertSame('PO-77', $request->po_number);
        $this->assertTrue($request->isApproved(ItemRequestApprovalType::Accounting));
        $this->assertSame(1, $this->workflow->scopeAwaitingExecutor(ItemRequest::query()->whereKey($request->id))->count());

        $this->workflow->approveManager($request, $this->staff);
        $this->assertEquals(4, $request->details()->first()->qty_approved);
        $this->assertTrue($request->refresh()->isApproved(ItemRequestApprovalType::Manager));

        $this->workflow->process($request, $this->staff, ItemRequestExecutorDecision::Processed, 'sedang dibeli');
        $this->assertSame(ItemRequestClientStatus::Processed, $request->refresh()->client_status);
        $this->assertNull($request->closed_at);

        $this->workflow->process($request, $this->staff, ItemRequestExecutorDecision::Closed, 'sudah diserahkan');
        $request->refresh();
        $this->assertSame(ItemRequestClientStatus::Closed, $request->client_status);
        $this->assertSame($this->staff->id, $request->closed_by_user_id);
        $this->assertNotNull($request->closed_at);

        $this->workflow->confirm($request, $this->requester, false, 'belum sampai');
        $this->assertSame(ItemRequestClientStatus::Closed, $request->refresh()->client_status);

        $this->workflow->confirm($request, $this->requester, true, 'diterima');
        $request->refresh();
        $this->assertSame(ItemRequestClientStatus::Accepted, $request->client_status);
        $this->assertTrue($request->isApproved(ItemRequestApprovalType::EndUser));
        $this->assertEquals(10000, $request->grandTotal());

        $this->expectException(ItemRequestException::class);
        $this->workflow->process($request, $this->staff, ItemRequestExecutorDecision::Pending);
    }

    public function test_cancel_and_change_applicant_are_logged(): void
    {
        $request = $this->createAtk();
        $other = User::factory()->create();

        $this->workflow->changeApplicant($request, $this->staff, $other);
        $this->assertSame($other->id, $request->refresh()->user_id);

        $this->workflow->cancel($request, $other, 'salah input');
        $this->assertSame(ItemRequestClientStatus::Cancelled, $request->refresh()->client_status);

        $activities = $request->logs()->pluck('activity')->all();
        $this->assertCount(2, $activities);
        $this->assertStringContainsString('merubah pemohon', implode(' ', $activities));
        $this->assertStringContainsString('membatalkan', implode(' ', $activities));

        $this->expectException(ItemRequestException::class);
        $this->workflow->approveManager($request, $this->staff);
    }

    private function createAtk(): ItemRequest
    {
        return $this->workflow->create($this->requester, [
            'item_request_type_id' => $this->atk->id,
            'items' => [['product_id' => $this->product->id, 'qty_request' => 1]],
        ]);
    }
}
