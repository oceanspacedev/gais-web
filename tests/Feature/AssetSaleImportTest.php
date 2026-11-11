<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Exports\AssetSaleExport;
use App\Filament\Resources\AssetSaleResource\Pages\ListAssetSales;
use App\Imports\AssetSaleImport;
use App\Models\Asset;
use App\Models\AssetSale;
use App\Models\BusinessEntity;
use App\Models\User;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use EightyNine\ExcelImport\SampleExcelExport;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Impor Excel penjualan massal antar badan usaha dan ekspor riwayatnya dari
 * menu Penjualan Aset: baris lewat AssetSale::record(), satu baris salah
 * membatalkan seluruh file, tombol mengikuti izin import_/export_asset::sale.
 */
class AssetSaleImportTest extends TestCase
{
    use DatabaseTransactions;

    private const PERMISSIONS = [
        'view_any_asset', 'view_asset',
        'view_any_asset::sale', 'view_asset::sale', 'create_asset::sale', 'import_asset::sale', 'export_asset::sale',
    ];

    private BusinessEntity $alpha;

    private BusinessEntity $beta;

    private BusinessEntity $gamma;

    private User $actor;

    /** @var list<string> */
    private array $scratchFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->alpha = BusinessEntity::create(['name' => '__asi_alpha__']);
        $this->beta = BusinessEntity::create(['name' => '__asi_beta__']);
        $this->gamma = BusinessEntity::create(['name' => '__asi_gamma__']);
        $this->actor = $this->userWith(self::PERMISSIONS, unrestricted: true);

        $this->actingAs($this->actor);
    }

    protected function tearDown(): void
    {
        foreach ($this->scratchFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_import_records_sales_by_id_or_serial_number_and_moves_ownership(): void
    {
        $byId = $this->stockAsset($this->alpha);
        $bySerial = $this->stockAsset($this->alpha, serial: 'SN-IMP-'.uniqid());
        $chained = $this->stockAsset($this->alpha);

        Livewire::test(ListAssetSales::class)
            ->assertActionVisible('importSales')
            ->callAction('importSales', data: ['upload' => $this->upload([
                // Nama badan usaha boleh beda huruf besar-kecil; harga boleh berpemisah ribuan.
                $this->row(['id_aset' => $byId->id, 'badan_usaha_penjual' => strtoupper($this->alpha->name), 'badan_usaha_pembeli' => strtoupper($this->beta->name), 'tanggal_penjualan' => '10/01/2026', 'harga_jual' => '1.500.000', 'catatan' => 'BAST-01']),
                $this->row(['serial_number' => $bySerial->serial_number, 'badan_usaha_pembeli' => $this->gamma->name, 'tanggal_penjualan' => '2026-01-11']),
                $this->row([]),
                // Berantai: aset yang sama dijual A→B lalu B→C dalam satu file.
                $this->row(['id_aset' => $chained->id, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-02-01', 'harga_jual' => 0]),
                $this->row(['id_aset' => $chained->id, 'badan_usaha_penjual' => $this->beta->name, 'badan_usaha_pembeli' => $this->gamma->name, 'tanggal_penjualan' => '2026-03-01', 'harga_jual' => 250000]),
            ])])
            ->assertHasNoActionErrors()
            // Satu pemeriksaan saja: membaca notifikasi mengosongkan antreannya di sesi.
            ->assertNotified('4 penjualan dicatat');

        $byId->refresh();
        $this->assertSame($this->beta->id, $byId->business_entity_id);
        $this->assertSame($this->beta->id, $byId->recipient_business_entity_id);
        $this->assertSame(AssetCondition::Available, $byId->condition_status);

        $sale = $byId->sales()->sole();
        $this->assertSame($this->alpha->id, $sale->from_business_entity_id);
        $this->assertSame('2026-01-10', $sale->sold_at->toDateString());
        $this->assertSame(1500000, $sale->sold_price);
        $this->assertSame('BAST-01', $sale->notes);
        $this->assertSame($this->actor->id, $sale->created_by);

        $this->assertSame($this->gamma->id, $bySerial->fresh()->business_entity_id);
        $this->assertSame(0, $bySerial->sales()->sole()->sold_price);

        $this->assertSame($this->gamma->id, $chained->fresh()->business_entity_id);
        $this->assertSame(
            [[$this->alpha->id, $this->beta->id], [$this->beta->id, $this->gamma->id]],
            $chained->sales()->get()->map(fn (AssetSale $sale): array => [$sale->from_business_entity_id, $sale->to_business_entity_id])->all(),
        );
    }

    public function test_one_bad_row_cancels_the_whole_file(): void
    {
        // Baris benar memakai aset lain supaya aset $ok tetap milik alpha di setiap kasus.
        $valid = $this->stockAsset($this->alpha);
        $ok = $this->stockAsset($this->alpha);
        $holder = User::factory()->create(['business_entity_id' => $this->alpha->id, 'name' => '__asi_holder__']);
        $held = Asset::create([
            'name' => '__asi_held__', 'business_entity_id' => $this->alpha->id, 'recipient_business_entity_id' => $this->alpha->id,
            'recipient_id' => $holder->id, 'condition_status' => AssetCondition::Transferred,
        ]);
        $validRow = $this->row(['id_aset' => $valid->id, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']);

        $cases = [
            'tidak ditemukan' => $this->row(['id_aset' => 999999999, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            'isi kolom id_aset atau serial_number' => $this->row(['badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            'harus berbeda dari pemilik' => $this->row(['id_aset' => $ok->id, 'badan_usaha_pembeli' => $this->alpha->name, 'tanggal_penjualan' => '2026-01-10']),
            'tidak sama dengan pemilik aset' => $this->row(['id_aset' => $ok->id, 'badan_usaha_penjual' => $this->gamma->name, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            'tidak ada di master Badan Usaha' => $this->row(['id_aset' => $ok->id, 'badan_usaha_pembeli' => '__asi_tidak_ada__', 'tanggal_penjualan' => '2026-01-10']),
            'badan_usaha_pembeli wajib diisi' => $this->row(['id_aset' => $ok->id, 'tanggal_penjualan' => '2026-01-10']),
            'tanggal_penjualan wajib diisi' => $this->row(['id_aset' => $ok->id, 'badan_usaha_pembeli' => $this->beta->name]),
            'BA Pengembalian' => $this->row(['id_aset' => $held->id, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            'tidak cocok dengan aset' => $this->row(['id_aset' => $ok->id, 'serial_number' => 'SN-SALAH', 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
        ];

        foreach ($cases as $expectedMessage => $badRow) {
            try {
                // Baris yang benar di depan ikut dibatalkan.
                Excel::import(new AssetSaleImport, $this->file([$validRow, $badRow]));
                $this->fail("Baris salah ({$expectedMessage}) harus menolak file.");
            } catch (ImportStoppedException $exception) {
                $this->assertStringContainsString('Baris 3', $exception->getUserMessage(), $expectedMessage);
                $this->assertStringContainsString($expectedMessage, $exception->getUserMessage());
            }

            $this->assertSame(0, AssetSale::query()->whereIn('asset_id', [$valid->id, $ok->id, $held->id])->count(), $expectedMessage);
            $this->assertSame($this->alpha->id, $valid->fresh()->business_entity_id, "{$expectedMessage}: baris benar di depan ikut dibatalkan.");
            $this->assertSame($this->alpha->id, $ok->fresh()->business_entity_id, $expectedMessage);
        }

        // Lewat panel: notifikasi gagal, modal tetap terbuka, tidak ada yang tersimpan.
        Livewire::test(ListAssetSales::class)
            ->callAction('importSales', data: ['upload' => $this->upload([$validRow, $cases['tidak ditemukan']])])
            ->assertNotified(__('excel-import::excel-import.import_failed'));

        $this->assertSame(0, AssetSale::query()->whereIn('asset_id', [$valid->id, $ok->id])->count());

        // File tanpa baris terisi: peringatan, bukan sukses palsu.
        try {
            Excel::import(new AssetSaleImport, $this->file([$this->row([])]));
            $this->fail('File kosong harus ditolak dengan peringatan.');
        } catch (ImportStoppedException $exception) {
            $this->assertSame('warning', $exception->getType());
        }
    }

    public function test_import_respects_the_business_entity_access_of_the_importer(): void
    {
        $asset = $this->stockAsset($this->alpha);
        $restricted = $this->userWith(self::PERMISSIONS, $this->alpha);
        $this->actingAs($restricted);

        try {
            Excel::import(new AssetSaleImport, $this->file([
                $this->row(['id_aset' => $asset->id, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            ]));
            $this->fail('Pembeli di luar akses pengimpor harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString($this->beta->name, $exception->getUserMessage());
        }

        $outsider = $this->stockAsset($this->gamma);

        try {
            Excel::import(new AssetSaleImport, $this->file([
                $this->row(['id_aset' => $outsider->id, 'badan_usaha_pembeli' => $this->alpha->name, 'tanggal_penjualan' => '2026-01-10']),
            ]));
            $this->fail('Aset di luar akses pengimpor harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString('di luar akses Anda', $exception->getUserMessage());
        }

        $this->assertSame(0, AssetSale::query()->whereIn('asset_id', [$asset->id, $outsider->id])->count());

        $this->actingAs($this->userWith(['view_any_asset', 'view_asset', 'view_any_asset::sale', 'import_asset::sale'], unrestricted: true));

        try {
            Excel::import(new AssetSaleImport, $this->file([
                $this->row(['id_aset' => $asset->id, 'badan_usaha_pembeli' => $this->beta->name, 'tanggal_penjualan' => '2026-01-10']),
            ]));
            $this->fail('Tanpa izin create_asset::sale impor harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString('izin', $exception->getUserMessage());
        }
    }

    public function test_export_lists_visible_sales_with_the_template_columns(): void
    {
        $asset = $this->stockAsset($this->alpha, serial: 'SN-EKS-'.uniqid());
        $sale = AssetSale::record($asset, ['to_business_entity_id' => $this->beta->id, 'sold_at' => '2026-01-10', 'sold_price' => 1500000, 'notes' => 'ekspor'], $this->actor);
        $unrelated = AssetSale::record($this->stockAsset($this->gamma), ['to_business_entity_id' => BusinessEntity::create(['name' => '__asi_delta__'])->id, 'sold_at' => '2026-01-11'], $this->actor);

        $this->assertSame(array_keys(AssetSaleImport::sampleRow()), array_slice((new AssetSaleExport)->headings(), 0, count(AssetSaleImport::sampleRow())));

        Livewire::test(ListAssetSales::class)
            ->assertActionVisible('exportAll')
            ->callAction('exportAll')
            ->assertFileDownloaded('penjualan-aset.xlsx');

        $rows = $this->readRows(new AssetSaleExport);
        $exported = collect($rows)->skip(1)->keyBy(fn (array $row): int => (int) $row[8]);

        $this->assertSame((new AssetSaleExport)->headings(), $rows[0]);
        $this->assertSame(
            [$asset->id, $asset->serial_number, $asset->name, $this->alpha->name, $this->beta->name, '2026-01-10', 1500000, 'ekspor', $sale->id, $this->actor->name],
            array_slice($exported[$sale->id], 0, 10),
        );
        $this->assertArrayHasKey($unrelated->id, $exported, 'Pembaca berakses penuh melihat semua penjualan.');

        // Pembaca berakses terbatas hanya mengekspor penjualan yang menyentuh badan usahanya.
        $this->actingAs($this->userWith(self::PERMISSIONS, $this->beta));
        $restrictedIds = collect($this->readRows(new AssetSaleExport))->skip(1)->map(fn (array $row): int => (int) $row[8])->all();

        $this->assertContains($sale->id, $restrictedIds);
        $this->assertNotContains($unrelated->id, $restrictedIds);
    }

    public function test_import_and_export_buttons_follow_their_permissions(): void
    {
        Livewire::test(ListAssetSales::class)
            ->assertActionVisible('importSales')
            ->assertActionVisible('exportAll');

        $this->actingAs($this->userWith(['view_any_asset::sale', 'view_asset::sale', 'create_asset::sale'], unrestricted: true));

        Livewire::test(ListAssetSales::class)
            ->assertOk()
            ->assertActionVisible('record')
            ->assertActionHidden('importSales')
            ->assertActionHidden('exportAll');
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions, ?BusinessEntity $entity = null, bool $unrestricted = false): User
    {
        $user = User::factory()->create([
            'business_entity_id' => $entity?->id,
            'access_all_business_entities' => $unrestricted,
        ]);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user->fresh();
    }

    private function stockAsset(BusinessEntity $owner, ?string $serial = null): Asset
    {
        return Asset::create([
            'name' => '__asi_asset_'.uniqid().'__',
            'business_entity_id' => $owner->id,
            'recipient_business_entity_id' => $owner->id,
            'condition_status' => AssetCondition::Available,
            'serial_number' => $serial,
        ]);
    }

    /**
     * Baris berkolom template; kolom yang tidak disebut kosong.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function row(array $values): array
    {
        return array_merge(array_fill_keys(array_keys(AssetSaleImport::sampleRow()), ''), $values);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upload(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('penjualan.xlsx', Excel::raw(new SampleExcelExport($rows), ExcelWriter::XLSX));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function file(array $rows): string
    {
        return $this->store(Excel::raw(new SampleExcelExport($rows), ExcelWriter::XLSX));
    }

    /**
     * @return list<list<mixed>>
     */
    private function readRows(AssetSaleExport $export): array
    {
        return Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, $this->store(Excel::raw($export, ExcelWriter::XLSX)))[0];
    }

    private function store(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'asset-sale-').'.xlsx';
        file_put_contents($path, $contents);
        $this->scratchFiles[] = $path;

        return $path;
    }
}
