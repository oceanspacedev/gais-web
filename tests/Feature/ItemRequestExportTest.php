<?php

namespace Tests\Feature;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Enums\ItemRequestRecapStatus;
use App\Filament\Resources\ItemRequestResource\Pages\ListItemRequests;
use App\Models\Area;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\Product;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ItemRequestMasterQrExport;
use App\Services\ItemRequestRecapExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Rekap per Divisi dan Ekspor Master QR dari daftar pengajuan barang.
 */
class ItemRequestExportTest extends TestCase
{
    use DatabaseTransactions;

    private Area $area;

    private Division $audit;

    private Division $finance;

    private Division $depo;

    private ItemRequestType $type;

    private Product $paper;

    private Product $pen;

    private User $alice;

    private User $bob;

    private User $carl;

    private User $viewer;

    private ItemRequest $auditRequest;

    private ItemRequest $financeClosed;

    private ItemRequest $financeCancelled;

    private ItemRequest $depoRequest;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->area = Area::create(['name' => 'HO-rekap']);
        $otherArea = Area::create(['name' => 'DEPO-rekap']);
        $this->audit = Division::create(['name' => 'Audit-rekap', 'area_id' => $this->area->id]);
        $this->finance = Division::create(['name' => 'Finance-rekap', 'area_id' => $this->area->id]);
        $this->depo = Division::create(['name' => 'Depo-rekap', 'area_id' => $otherArea->id]);

        $rim = UnitType::create(['name' => 'Rim-rekap']);
        $this->type = ItemRequestType::create(['name' => 'ATK-rekap', 'requires_products' => true]);
        $this->paper = Product::create(['name' => 'Kertas-rekap', 'price' => 50000, 'unit_type_id' => $rim->id]);
        $this->pen = Product::create(['name' => 'Pulpen-rekap', 'price' => 2500]);

        $this->alice = User::factory()->create(['name' => 'Alice Rekap', 'division_id' => $this->audit->id]);
        $this->bob = User::factory()->create(['name' => 'Bob Rekap', 'division_id' => $this->finance->id]);
        $this->carl = User::factory()->create(['name' => 'Carl Rekap', 'division_id' => $this->depo->id]);
        $this->viewer = $this->userWithRole('pengajuan_admin');

        $this->seedRequests();
    }

    public function test_recap_sums_effective_quantities_per_division_and_adds_footer_rows(): void
    {
        $export = $this->recap();

        $this->assertSame(
            ['Barang', 'Tipe Unit', 'Harga', 'Audit-rekap', 'Finance-rekap', 'Total Item', 'Total Biaya'],
            $export->headings(),
        );

        // Audit: Kertas disetujui 2 (diminta 3), Pulpen 10 tanpa persetujuan jumlah.
        // Finance: Kertas 5 dari pengajuan selesai; Pulpen 4 yang dibatalkan tidak ikut.
        $this->assertSame([
            ['Kertas-rekap', 'Rim-rekap', 50000, 2, 5, 7, 350000],
            ['Pulpen-rekap', null, 2500, 10, 0, 10, 25000],
            [ItemRequestRecapExport::TOTAL_ITEMS_LABEL, null, null, 12, 5, 17, null],
            [ItemRequestRecapExport::TOTAL_COST_LABEL, null, null, 125000, 250000, null, 375000],
        ], $export->array());
    }

    public function test_recap_status_filter_follows_the_sumo_semantics(): void
    {
        $this->assertSame(
            ['Kertas-rekap' => [2, 0], 'Pulpen-rekap' => [10, 0]],
            $this->quantities(ItemRequestRecapStatus::AwaitingExecutor),
        );
        $this->assertSame(['Kertas-rekap' => [0, 5]], $this->quantities(ItemRequestRecapStatus::ProcessedByExecutor));
        $this->assertSame(['Pulpen-rekap' => [0, 4]], $this->quantities(ItemRequestRecapStatus::Cancelled));
        $this->assertSame(['Kertas-rekap' => [0, 5]], $this->quantities(ItemRequestRecapStatus::Closed));
    }

    public function test_recap_only_counts_requests_the_viewer_may_see(): void
    {
        // Tanpa izin lihat semua, Bob hanya melihat pengajuannya sendiri.
        $this->assertSame(['Kertas-rekap' => [0, 5]], $this->quantities(viewer: $this->bob));
    }

    public function test_recap_without_matching_requests_still_has_division_columns_and_footer(): void
    {
        $export = $this->recap(from: now()->addYear()->startOfMonth(), until: now()->addYear()->endOfMonth());

        $this->assertSame([
            [ItemRequestRecapExport::TOTAL_ITEMS_LABEL, null, null, 0, 0, 0, null],
            [ItemRequestRecapExport::TOTAL_COST_LABEL, null, null, 0, 0, null, 0],
        ], $export->array());
    }

    public function test_recap_is_written_as_an_xlsx_sheet(): void
    {
        $export = $this->recap();
        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';

        try {
            file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));
            $sheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame('Rekap per Divisi', $sheet->getTitle());
            $this->assertEquals([$export->headings(), ...$export->array()], $sheet->toArray(formatData: false));
        } finally {
            @unlink($path);
        }

        $this->assertSame(
            'rekap-pengajuan-atk-rekap-ho-rekap-'.now()->startOfMonth()->format('Ymd').'-'.now()->endOfMonth()->format('Ymd').'.xlsx',
            $export->fileName(),
        );
    }

    public function test_master_qr_lists_one_row_per_detail_with_product_and_requester(): void
    {
        $export = new ItemRequestMasterQrExport($this->viewer, $this->type, now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(['id', 'nama_barang', 'pemohon'], $export->headings());

        [$auditPaper, $auditPen] = $this->auditRequest->details()->orderBy('id')->pluck('id')->all();

        // Dibatalkan dan area lain tetap ikut (tanpa filter area, seperti sumo);
        // bulan lalu, detail terhapus, dan pengajuan terhapus tidak.
        $this->assertSame([
            [$auditPaper, 'Kertas-rekap', 'Alice Rekap'],
            [$auditPen, 'Pulpen-rekap', 'Alice Rekap'],
            [$this->financeClosed->details()->value('id'), 'Kertas-rekap', 'Bob Rekap'],
            [$this->financeCancelled->details()->value('id'), 'Pulpen-rekap', 'Bob Rekap'],
            [$this->depoRequest->details()->value('id'), 'Kertas-rekap', 'Carl Rekap'],
        ], $export->array());
    }

    public function test_export_actions_are_hidden_without_the_export_permission(): void
    {
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(ListItemRequests::class)
            ->assertOk()
            ->assertActionHidden('exportRecap')
            ->assertActionHidden('exportMasterQr');
    }

    public function test_export_actions_download_xlsx_files_for_users_with_the_permission(): void
    {
        $this->actingAs($this->viewer);

        $range = ['from' => now()->startOfMonth()->toDateString(), 'until' => now()->endOfMonth()->toDateString()];
        $dates = now()->startOfMonth()->format('Ymd').'-'.now()->endOfMonth()->format('Ymd');

        Livewire::test(ListItemRequests::class)
            ->assertOk()
            ->assertActionVisible('exportRecap')
            ->assertActionVisible('exportMasterQr')
            ->callAction('exportRecap', [
                'area_id' => $this->area->id,
                'item_request_type_id' => $this->type->id,
                'status' => ItemRequestRecapStatus::NotCancelled->value,
                ...$range,
            ])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded("rekap-pengajuan-atk-rekap-ho-rekap-{$dates}.xlsx")
            ->callAction('exportMasterQr', ['item_request_type_id' => $this->type->id, ...$range])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded("master-qr-pengajuan-atk-rekap-{$dates}.xlsx");
    }

    public function test_recap_action_requires_area_and_type(): void
    {
        $this->actingAs($this->viewer);

        Livewire::test(ListItemRequests::class)
            ->callAction('exportRecap', ['area_id' => null, 'item_request_type_id' => null])
            ->assertHasActionErrors(['area_id' => 'required', 'item_request_type_id' => 'required']);
    }

    /**
     * Audit: satu pengajuan menunggu (Kertas 3 → disetujui 2, Pulpen 10).
     * Finance: satu selesai oleh executor (Kertas 5), satu dibatalkan (Pulpen 4),
     * satu yang detailnya dihapus (Pulpen 9).
     * Di luar hitungan: area lain (Kertas 7), bulan lalu (Kertas 1), pengajuan terhapus (Kertas 100).
     */
    private function seedRequests(): void
    {
        $this->auditRequest = $this->request($this->alice, [[$this->paper, 3, 2], [$this->pen, 10, null]]);

        $this->financeClosed = $this->request($this->bob, [[$this->paper, 5, null]]);
        $this->financeClosed->approval(ItemRequestApprovalType::Executor)->markApproved($this->viewer);
        $this->financeClosed->forceFill(['client_status' => ItemRequestClientStatus::Closed])->save();

        $this->financeCancelled = $this->request($this->bob, [[$this->pen, 4, null]]);
        $this->financeCancelled->forceFill(['client_status' => ItemRequestClientStatus::Cancelled])->save();

        $this->request($this->bob, [[$this->pen, 9, null]])->details()->delete();

        $this->depoRequest = $this->request($this->carl, [[$this->paper, 7, null]]);
        $this->request($this->alice, [[$this->paper, 1, null]], now()->startOfMonth()->subDay());
        $this->request($this->alice, [[$this->paper, 100, null]])->delete();
    }

    /**
     * @param  list<array{0: Product, 1: int, 2: int|null}>  $items  barang, jumlah diminta, jumlah disetujui
     */
    private function request(User $requester, array $items, ?Carbon $requestedAt = null): ItemRequest
    {
        $request = ItemRequest::create([
            'user_id' => $requester->id,
            'item_request_type_id' => $this->type->id,
            'requested_at' => $requestedAt ?? now(),
        ]);

        foreach ($items as [$product, $requested, $approved]) {
            $request->details()->create([
                'product_id' => $product->id,
                'qty_request' => $requested,
                'qty_approved' => $approved,
            ]);
        }

        return $request;
    }

    private function recap(
        ItemRequestRecapStatus $status = ItemRequestRecapStatus::NotCancelled,
        ?User $viewer = null,
        ?Carbon $from = null,
        ?Carbon $until = null,
    ): ItemRequestRecapExport {
        return new ItemRequestRecapExport(
            $viewer ?? $this->viewer,
            $this->area,
            $this->type,
            $from ?? now()->startOfMonth(),
            $until ?? now()->endOfMonth(),
            $status,
        );
    }

    /**
     * Jumlah per divisi (Audit, Finance) per barang, tanpa baris footer.
     *
     * @return array<string, list<int|float>>
     */
    private function quantities(ItemRequestRecapStatus $status = ItemRequestRecapStatus::NotCancelled, ?User $viewer = null): array
    {
        $rows = array_slice($this->recap($status, $viewer)->array(), 0, -2);
        $quantities = [];

        foreach ($rows as $row) {
            $quantities[$row[0]] = [$row[3], $row[4]];
        }

        return $quantities;
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role, 'web'));

        return $user;
    }
}
