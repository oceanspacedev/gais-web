<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Exports\InsuranceUpdateExport;
use App\Exports\RentUpdateExport;
use App\Filament\Resources\InsuranceResource\Pages\ViewInsurance;
use App\Filament\Resources\InsuranceResource\RelationManagers\UpdatesRelationManager as InsuranceUpdatesRelationManager;
use App\Filament\Resources\RentResource\Pages\ViewRent;
use App\Filament\Resources\RentResource\RelationManagers\UpdatesRelationManager as RentUpdatesRelationManager;
use App\Imports\InsuranceUpdateImport;
use App\Imports\RentUpdateImport;
use App\Models\Insurance;
use App\Models\InsuranceProvider;
use App\Models\InsuranceUpdate;
use App\Models\Rent;
use App\Models\RentUpdate;
use App\Models\User;
use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use EightyNine\ExcelImport\SampleExcelExport;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
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
 * Impor dan ekspor baris pembaharuan asuransi / perpanjangan sewa per kontrak
 * (sumo: importUpdate, templateUpdate, exportUpdate) dari tab Riwayat.
 */
class ContractRenewalImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;

    /** @var list<string> */
    private array $scratchFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->manager = $this->userWith([
            'view_any_insurance', 'view_insurance', 'update_insurance', 'import_insurance', 'export_insurance',
            'view_any_rent', 'view_rent', 'update_rent', 'import_rent', 'export_rent',
        ]);

        $this->actingAs($this->manager);
    }

    protected function tearDown(): void
    {
        foreach ($this->scratchFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_insurance_renewals_are_imported_into_the_open_policy_and_existing_rows_keep_blank_cells(): void
    {
        $insurance = $this->insurance('POL-IMP-1');
        $other = $this->insurance('POL-IMP-LAIN');
        // Provider harus sudah ada di master; nama di file boleh beda huruf besar-kecil.
        InsuranceProvider::create(['name' => 'Provider Impor']);
        $other->updates()->create(['policy_number' => 'POL-IMP-1A', 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31']);
        $existing = $insurance->updates()->create([
            'policy_number' => 'POL-IMP-1A', 'stock_premium' => 100, 'notes' => 'catatan lama',
            'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'status' => ContractStatus::Renewed->value,
        ]);

        Livewire::test(InsuranceUpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->assertOk()
            ->callTableAction('importUpdates', data: ['upload' => $this->upload([
                // Induk ditulis huruf kecil: pencocokan mengabaikan huruf besar-kecil; sel kosong dibiarkan.
                $this->insuranceRow(['no_polis_induk' => 'pol-imp-1', 'no_polis' => 'POL-IMP-1A', 'asuransi_stok' => 'provider impor', 'nilai_stok' => '150.000.000']),
                $this->insuranceRow(['no_polis_induk' => 'POL-IMP-1', 'no_polis' => 'POL-IMP-1B', 'nilai_stok' => 200000000, 'premi_stok' => 500000, 'tanggal_mulai' => '01/01/2027', 'tanggal_akhir' => '2027-12-31', 'catatan' => 'baru']),
                $this->insuranceRow([]),
            ])])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('excel-import::excel-import.import_success'));

        $existing->refresh();
        $this->assertSame(150000000, $existing->stock_worth);
        $this->assertSame(100, $existing->stock_premium, 'Sel kosong tidak menimpa nilai lama.');
        $this->assertSame('catatan lama', $existing->notes);
        $this->assertSame(ContractStatus::Renewed, $existing->status, 'Status kosong pada baris lama dibiarkan.');
        $this->assertSame('Provider Impor', $existing->stockProvider?->name);
        $this->assertSame(1, InsuranceProvider::query()->whereRaw('LOWER(name) = ?', ['provider impor'])->count(), 'Impor tidak membuat provider baru.');
        $this->assertSame($this->manager->id, $existing->user_id);

        $created = InsuranceUpdate::query()->where('insurance_id', $insurance->id)->where('policy_number', 'POL-IMP-1B')->firstOrFail();
        $this->assertSame(ContractStatus::Running, $created->status);
        $this->assertSame('2027-01-01', $created->join_date?->toDateString());
        $this->assertSame('2027-12-31', $created->expired_date?->toDateString());
        $this->assertSame(500000, $created->stock_premium);
        $this->assertSame('baru', $created->notes);

        $this->assertSame(2, $insurance->updates()->count(), 'Baris kosong dilewati.');
        $this->assertNull($other->updates()->sole()->stock_worth, 'Pembaharuan polis lain dengan nomor sama tidak disentuh.');
    }

    public function test_insurance_import_rejects_rows_for_another_policy_and_new_rows_without_dates(): void
    {
        $insurance = $this->insurance('POL-TOLAK-1');
        $this->insurance('POL-TOLAK-LAIN');

        try {
            Excel::import(InsuranceUpdateImport::for($insurance), $this->file([
                $this->insuranceRow(['no_polis_induk' => 'POL-TOLAK-LAIN', 'no_polis' => 'X-1', 'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-12-31']),
            ]));
            $this->fail('Baris untuk polis lain harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString('Baris 2', $exception->getUserMessage());
            $this->assertStringContainsString('POL-TOLAK-LAIN', $exception->getUserMessage());
        }

        try {
            Excel::import(InsuranceUpdateImport::for($insurance), $this->file([
                $this->insuranceRow(['no_polis_induk' => 'POL-TOLAK-1', 'no_polis' => 'OK-1', 'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-12-31']),
                $this->insuranceRow(['no_polis_induk' => 'POL-TOLAK-1', 'no_polis' => 'TANPA-TANGGAL']),
            ]));
            $this->fail('Baris baru tanpa tanggal harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString('Baris 3', $exception->getUserMessage());
            $this->assertStringContainsString('tanggal_mulai', $exception->getUserMessage());
        }

        $this->assertSame(0, $insurance->updates()->count(), 'Satu baris salah membatalkan seluruh file.');

        // Lewat panel, kesalahan tampil sebagai notifikasi dan tidak ada yang tersimpan.
        Livewire::test(InsuranceUpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->callTableAction('importUpdates', data: ['upload' => $this->upload([
                $this->insuranceRow(['no_polis_induk' => '', 'no_polis' => 'X-2', 'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-12-31']),
            ])])
            ->assertNotified(__('excel-import::excel-import.import_failed'));

        $this->assertSame(0, $insurance->updates()->count());
    }

    public function test_rent_renewals_match_the_parent_code_and_blank_codes_are_generated(): void
    {
        $rent = $this->rent('RENT-IMP-1');
        $existing = $rent->updates()->create([
            'rent_code' => 'RENTUP-LAMA', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 1000,
            'join_date' => '2026-01-01', 'expired_date' => '2026-12-31',
        ]);

        Livewire::test(RentUpdatesRelationManager::class, ['ownerRecord' => $rent, 'pageClass' => ViewRent::class])
            ->assertOk()
            ->callTableAction('importUpdates', data: ['upload' => $this->upload([
                $this->rentRow(['kode_sewa_induk' => 'rent-imp-1', 'kode' => 'RENTUP-LAMA', 'dana_cvcs' => 5000000, 'status' => 'PEMBAHARUAN']),
                $this->rentRow(['kode_sewa_induk' => 'RENT-IMP-1', 'sewa_per_tahun' => '15.000.000', 'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-12-31', 'reminder_bulan_sebelumnya' => 2, 'bukti_potong' => 'ADA']),
            ])])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('excel-import::excel-import.import_success'));

        $existing->refresh();
        $this->assertSame(1000, $existing->rent_per_year, 'Sel kosong tidak menimpa nilai lama.');
        $this->assertSame(5000000, $existing->cvcs_fund);
        $this->assertSame(ContractStatus::Renewed, $existing->status);

        $created = RentUpdate::query()->where('rent_id', $rent->id)->where('id', '!=', $existing->id)->sole();
        $this->assertMatchesRegularExpression('/^RENTUP\d{4}$/', $created->rent_code, 'Kode kosong dibuat otomatis.');
        $this->assertSame($rent->first_party, $created->first_party, 'Pihak kosong pada baris baru diambil dari induk.');
        $this->assertSame($rent->second_party, $created->second_party);
        $this->assertSame(15000000, $created->rent_per_year);
        $this->assertSame(ContractStatus::Running, $created->status);
        $this->assertSame('2', $created->month_before_reminder);
        $this->assertSame('ADA', $created->deduction_evidence);
        $this->assertSame('2027-12-31', $created->expired_date?->toDateString());

        try {
            Excel::import(RentUpdateImport::for($rent), $this->file([
                $this->rentRow(['kode_sewa_induk' => 'RENT-LAIN', 'tanggal_mulai' => '2027-01-01', 'tanggal_akhir' => '2027-12-31']),
            ]));
            $this->fail('Baris untuk sewa lain harus ditolak.');
        } catch (ImportStoppedException $exception) {
            $this->assertStringContainsString('RENT-LAIN', $exception->getUserMessage());
        }

        $this->assertSame(2, $rent->updates()->count());
    }

    public function test_exports_use_the_template_headings_and_round_trip_through_the_importer(): void
    {
        $insurance = $this->insurance('POL/EKS/1');
        $provider = InsuranceProvider::create(['name' => 'Provider Ekspor']);
        $insurance->updates()->create([
            'policy_number' => 'POL/EKS/1A', 'stock_provider_id' => $provider->id, 'stock_worth' => 123000, 'stock_premium' => 4500,
            'join_date' => '2027-01-01', 'expired_date' => '2027-12-31', 'status' => ContractStatus::Renewed->value, 'notes' => 'ekspor',
        ]);
        $rent = $this->rent('RENT-EKS-1');
        $rent->updates()->create(['rent_code' => 'RENTUP-EKS', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 7, 'join_date' => '2027-01-01', 'expired_date' => '2027-12-31', 'month_before_reminder' => '3']);

        $this->assertSame(array_keys(InsuranceUpdateImport::sampleRow()), (new InsuranceUpdateExport($insurance))->headings());
        $this->assertSame(array_keys(RentUpdateImport::sampleRow()), (new RentUpdateExport($rent))->headings());

        Livewire::test(InsuranceUpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->callTableAction('exportUpdates')
            ->assertFileDownloaded('pembaharuan-asuransi-POL-EKS-1.xlsx');
        Livewire::test(RentUpdatesRelationManager::class, ['ownerRecord' => $rent, 'pageClass' => ViewRent::class])
            ->callTableAction('exportUpdates')
            ->assertFileDownloaded('perpanjangan-sewa-RENT-EKS-1.xlsx');

        $insurancePath = $this->store(Excel::raw(new InsuranceUpdateExport($insurance), ExcelWriter::XLSX));
        $rows = Excel::toArray(new class implements ToArray
        {
            public function array(array $array): void {}
        }, $insurancePath)[0];

        $this->assertSame((new InsuranceUpdateExport($insurance))->headings(), $rows[0]);
        $this->assertSame(['POL/EKS/1', 'POL/EKS/1A', 'Provider Ekspor', 123000, null, 4500, null, null, null, '2027-01-01', '2027-12-31', 'ekspor', 'PEMBAHARUAN'], $rows[1]);

        // Hasil ekspor diimpor kembali tanpa diubah: tidak ada baris baru, nilai tetap.
        Excel::import(InsuranceUpdateImport::for($insurance), $insurancePath);
        Excel::import(RentUpdateImport::for($rent), $this->store(Excel::raw(new RentUpdateExport($rent), ExcelWriter::XLSX)));

        $this->assertSame(1, $insurance->updates()->count());
        $this->assertSame(1, $rent->updates()->count());
        $this->assertSame(ContractStatus::Renewed, $insurance->updates()->sole()->status);
        $this->assertSame('3', $rent->updates()->sole()->month_before_reminder);
        $this->assertSame(7, $rent->updates()->sole()->rent_per_year);
    }

    public function test_import_and_export_actions_follow_the_contract_permissions(): void
    {
        $insurance = $this->insurance('POL-IZIN-1');
        $rent = $this->rent('RENT-IZIN-1');

        $insuranceManager = Livewire::test(InsuranceUpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->assertTableActionVisible('importUpdates')
            ->assertTableActionVisible('exportUpdates')
            ->mountTableAction('importUpdates');
        $this->assertSame('Impor Pembaharuan', $insuranceManager->instance()->getMountedAction()?->getModalHeading());
        $this->assertSame('Unduh template', $this->templateHintAction($insuranceManager->instance())->getLabel());

        $rentManager = Livewire::test(RentUpdatesRelationManager::class, ['ownerRecord' => $rent, 'pageClass' => ViewRent::class])
            ->assertTableActionVisible('importUpdates')
            ->assertTableActionVisible('exportUpdates')
            ->mountTableAction('importUpdates');
        $this->assertSame('Impor Perpanjangan', $rentManager->instance()->getMountedAction()?->getModalHeading());
        $this->assertSame('Unduh template', $this->templateHintAction($rentManager->instance())->getLabel());

        // Template yang diunduh sudah berisi kode kontrak yang sedang dibuka.
        $this->assertSame('POL-IZIN-1', InsuranceUpdateImport::sampleRow($insurance)['no_polis_induk']);
        $this->assertSame('RENT-IZIN-1', RentUpdateImport::sampleRow($rent)['kode_sewa_induk']);

        $this->actingAs($this->userWith(['view_any_insurance', 'view_insurance', 'update_insurance', 'view_any_rent', 'view_rent', 'update_rent']));

        Livewire::test(InsuranceUpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->assertTableActionVisible('create')
            ->assertTableActionHidden('importUpdates')
            ->assertTableActionHidden('exportUpdates');
        Livewire::test(RentUpdatesRelationManager::class, ['ownerRecord' => $rent, 'pageClass' => ViewRent::class])
            ->assertTableActionVisible('create')
            ->assertTableActionHidden('importUpdates')
            ->assertTableActionHidden('exportUpdates');
    }

    /**
     * Tombol unduh template pada field unggah modal impor yang sedang terbuka.
     */
    private function templateHintAction(InsuranceUpdatesRelationManager|RentUpdatesRelationManager $manager): Action
    {
        $schema = $manager->getSchema($manager->getMountedActionSchemaName());
        $upload = collect($schema->getFlatComponents(withHidden: true))->first(fn ($component): bool => $component instanceof FileUpload);

        $this->assertInstanceOf(FileUpload::class, $upload);
        $this->assertSame('File Excel', $upload->getLabel());

        return collect($upload->getHintActions())->sole();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = User::factory()->create(['access_all_business_entities' => true]);
        $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

        return $user;
    }

    private function insurance(string $policyNumber): Insurance
    {
        return Insurance::create([
            'policy_number' => $policyNumber, 'insured_name' => 'Tertanggung '.$policyNumber,
            'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'status' => ContractStatus::Running->value,
        ]);
    }

    private function rent(string $code): Rent
    {
        return Rent::create([
            'rent_code' => $code, 'rented_detail' => 'Ruko '.$code, 'first_party' => 'PT Induk', 'second_party' => 'Pemilik Induk',
            'rent_per_year' => 12000000, 'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'status' => ContractStatus::Running->value,
        ]);
    }

    /**
     * Baris berkolom template asuransi; kolom yang tidak disebut kosong.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function insuranceRow(array $values): array
    {
        return array_merge(array_fill_keys(array_keys(InsuranceUpdateImport::sampleRow()), ''), $values);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function rentRow(array $values): array
    {
        return array_merge(array_fill_keys(array_keys(RentUpdateImport::sampleRow()), ''), $values);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upload(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pembaharuan.xlsx', Excel::raw(new SampleExcelExport($rows), ExcelWriter::XLSX));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function file(array $rows): string
    {
        return $this->store(Excel::raw(new SampleExcelExport($rows), ExcelWriter::XLSX));
    }

    private function store(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'renewal-').'.xlsx';
        file_put_contents($path, $contents);
        $this->scratchFiles[] = $path;

        return $path;
    }
}
