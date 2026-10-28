<?php

namespace Tests\Feature;

use App\Models\BusinessEntity;
use App\Models\ItemRequest;
use App\Models\ItemRequestScope;
use App\Models\ProblemReport;
use App\Models\User;
use App\Services\Sumo\SumoImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SumoLegacyFixture;
use Tests\TestCase;

/**
 * Id fixture dimulai dari 9.000.000 supaya tidak bentrok dengan data sumo yang
 * sudah diimpor ke database pengembangan (id sumo dipertahankan oleh importer).
 */
class SumoImportTest extends TestCase
{
    use DatabaseTransactions;

    private const B = 9000000;

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SumoLegacyFixture::install();

        // Database pengembangan bisa sudah berisi impor sumo sungguhan. Tanda legacy
        // dilepas di dalam transaksi supaya fixture tetap dihitung sebagai impor pertama
        // (cakupan pengajuan hanya diisi pada impor pertama).
        DB::table('users')->whereNotNull('legacy_sumo_user_id')->update(['legacy_sumo_user_id' => null]);

        $this->entity = BusinessEntity::create(['name' => 'PT Uji Sumo', 'format' => 'UJI']);

        config([
            'sumo-import.business_entities' => [self::B + 1 => null, self::B + 2 => $this->entity->id],
            'sumo-import.scopes' => [self::B + 6 => [[null, null]], self::B + 9 => [[self::B + 2, self::B + 4]]],
            'sumo-import.division_permissions' => ['HCM-UJI' => ['view_any_insurance', 'view_insurance', 'import_insurance']],
            'sumo-import.legacy_user_permissions' => [self::B + 4 => ['export_item::request']],
        ]);

        $this->seedLegacy();
    }

    public function test_import_maps_users_requests_and_contracts_and_is_repeatable(): void
    {
        $report = (new SumoImporter)->run();

        $admin = User::where('legacy_sumo_user_id', self::B + 1)->firstOrFail();
        $hcm = User::where('legacy_sumo_user_id', self::B + 2)->firstOrFail();
        $requester = User::where('legacy_sumo_user_id', self::B + 3)->firstOrFail();
        $warehouse = User::where('legacy_sumo_user_id', self::B + 4)->firstOrFail();
        $gone = User::where('legacy_sumo_user_id', self::B + 5)->firstOrFail();

        $this->assertTrue($admin->hasRole('pengajuan_admin'));
        $this->assertTrue($admin->access_all_business_entities);
        $this->assertTrue($hcm->hasRole('executor'));
        $this->assertTrue($hcm->can('import_insurance'));
        $this->assertSame(self::B + 6, $hcm->division_id);
        // Aturan divisi sumo hanya untuk executor: staf biasa di HCM tidak ikut mengelola asuransi.
        $hcmStaff = User::where('legacy_sumo_user_id', self::B + 6)->firstOrFail();
        $this->assertTrue($hcmStaff->hasRole('requester'));
        $this->assertFalse($hcmStaff->can('view_insurance'));
        $this->assertTrue($requester->hasRole('requester'));
        $this->assertFalse($requester->access_all_business_entities);
        $this->assertSame($this->entity->id, $requester->business_entity_id);
        $this->assertSame($hcm->id, $requester->approver_user_id);
        $this->assertTrue(Hash::check('rahasia', $requester->password));
        $this->assertTrue($warehouse->can('export_item::request'));
        $this->assertSame('gone-uji-nonaktif-sumo'.(self::B + 5), $gone->username);
        $this->assertCount(0, $gone->roles);
        // Akun terhapus tanpa riwayat apa pun tidak perlu dibuat.
        $this->assertSame(0, User::where('legacy_sumo_user_id', self::B + 8)->count());
        $this->assertSame(1, $report->count('users.skipped_unused'));

        $request = ItemRequest::with('details', 'approvals', 'logs')->findOrFail(self::B + 10);
        $this->assertSame('REQ'.(self::B + 10), $request->request_code);
        $this->assertSame($requester->id, $request->user_id);
        $this->assertSame($this->entity->id, $request->business_entity_id);
        $this->assertSame(2, $request->details->count());
        $this->assertSame(4, $request->approvals->count());
        $this->assertSame($hcm->id, $request->approvals->firstWhere('approval_type', 'EXECUTOR')->approved_by_user_id);
        $this->assertSame(3, $request->client_status->value);
        $this->assertTrue($request->po_status);
        $this->assertSame($admin->id, $request->logs->first()->user_id);
        $this->assertSame(1, $report->count('item_request_details.orphan_skipped'));

        $this->assertNull(ItemRequest::findOrFail(self::B + 11)->user_id, 'Pengajuan dengan pemohon hilang tetap diimpor tanpa user.');

        $problem = ProblemReport::findOrFail(self::B + 20);
        $this->assertSame('PR'.(self::B + 20), $problem->code);
        $this->assertSame($requester->id, $problem->user_id);
        $this->assertSame('CLOSED', $problem->status->value);
        $this->assertSame(self::B + 7, $problem->problem_report_category_id);

        $this->assertSame(1, DB::table('insurances')->where('id', self::B + 30)->where('status', 'BERJALAN')->count());
        $this->assertNull(DB::table('insurances')->where('id', self::B + 30)->value('join_date'), 'Tanggal 0000-00-00 menjadi null.');
        $this->assertSame(1, DB::table('insurance_updates')->where('insurance_id', self::B + 30)->count());
        $this->assertSame(1, DB::table('rents')->where('id', self::B + 40)->count());
        $this->assertSame(1, DB::table('rent_updates')->where('rent_id', self::B + 40)->count());
        $this->assertSame('Pengajuan ATK Uji', DB::table('item_request_settings')->where('id', self::B + 1)->value('request_detail'));
        $this->assertEquals(1, DB::table('item_request_types')->where('id', self::B + 1)->value('requires_accounting_approval'));
        $this->assertEquals(1, DB::table('item_request_types')->where('id', self::B + 2)->value('requires_products'));
        $this->assertSame(2, ItemRequestScope::query()->where('division_id', '>=', self::B)->count());

        // Perubahan di GAIS sesudah impor pertama tidak boleh ditimpa impor ulang.
        DB::table('problem_reports')->where('id', self::B + 20)->update(['status' => 'CANCELLED', 'result_desc' => 'diubah di GAIS']);
        DB::table('item_requests')->where('id', self::B + 10)->update(['client_status' => 1, 'notes' => 'diproses di GAIS']);
        DB::table('item_request_approvals')->where('item_request_id', self::B + 10)->where('approval_type', 'MANAGER')->update(['approved_by_user_id' => $admin->id]);
        DB::table('item_request_types')->where('id', self::B + 1)->update(['name' => 'Diganti di GAIS']);
        DB::table('users')->where('id', $requester->id)->update(['approver_user_id' => $admin->id]);
        $requester->removeRole('requester');
        ItemRequestScope::query()->where('division_id', self::B + 9)->delete();

        $usersBefore = User::count();
        $rerun = (new SumoImporter)->run();

        $this->assertSame($usersBefore, User::count(), 'Impor ulang tidak membuat user ganda.');
        $this->assertSame('diubah di GAIS', DB::table('problem_reports')->where('id', self::B + 20)->value('result_desc'));
        $this->assertSame('CANCELLED', DB::table('problem_reports')->where('id', self::B + 20)->value('status'));
        $this->assertSame('diproses di GAIS', DB::table('item_requests')->where('id', self::B + 10)->value('notes'));
        $this->assertEquals(1, DB::table('item_requests')->where('id', self::B + 10)->value('client_status'));
        $this->assertEquals($admin->id, DB::table('item_request_approvals')->where('item_request_id', self::B + 10)->where('approval_type', 'MANAGER')->value('approved_by_user_id'));
        $this->assertSame('Diganti di GAIS', DB::table('item_request_types')->where('id', self::B + 1)->value('name'));
        $this->assertEquals($admin->id, DB::table('users')->where('id', $requester->id)->value('approver_user_id'));
        $this->assertFalse($requester->fresh()->hasRole('requester'), 'Role yang dicabut di GAIS tidak dipasang lagi.');
        $this->assertSame(0, ItemRequestScope::query()->where('division_id', self::B + 9)->count(), 'Cakupan yang dihapus di GAIS tidak diisi lagi.');
        $this->assertSame(0, $rerun->count('item_requests'));
        $this->assertSame(2, $rerun->count('item_requests.existing'));
        $this->assertSame(0, $rerun->count('users.created'));
        $this->assertSame(2, ItemRequest::findOrFail(self::B + 10)->details()->count());
        $this->assertSame(4, ItemRequest::findOrFail(self::B + 10)->approvals()->count());
        $this->assertSame(1, ItemRequestScope::query()->where('division_id', '>=', self::B)->count());
    }

    public function test_existing_gais_people_are_reused_instead_of_duplicated(): void
    {
        $b = self::B;
        $f = SumoLegacyFixture::class;
        $hash = Hash::make('rahasia-sumo', ['rounds' => 4]);
        $sumoUser = fn (int $id, string $username, string $fullname, int $role = 4): int => $f::insert('users', [
            'id' => $b + $id, 'username' => $username, 'fullname' => $fullname, 'password' => $hash,
            'badan_usaha_id' => $b + 2, 'division_id' => $b + 15, 'role_id' => $role, 'approval_id' => 0,
        ]);

        // Data karyawan GAIS tanpa login: digabung dan dilengkapi login sumo.
        $employee = User::factory()->create(['name' => 'PEGAWAI GABUNG UJI', 'username' => null, 'password' => null, 'business_entity_id' => null]);
        $sumoUser(50, 'pegawai-uji', 'Pegawai  Gabung Uji', 3);

        // Akun GAIS aktif dengan role non-sumo: digabung lewat username, login dan cakupan tidak diubah.
        $gaisAdmin = User::factory()->create(['name' => 'ADMIN GAIS UJI', 'username' => 'admin-gais-uji', 'access_all_business_entities' => false]);
        $gaisAdmin->assignRole(Role::findOrCreate('gais-lain-uji', 'web'));
        $sumoUser(51, 'admin-gais-uji', 'ADMIN GAIS UJI', 1);

        // Nama dipakai dua user GAIS: tidak ditebak, dibuat akun baru.
        User::factory()->count(2)->create(['name' => 'NAMA KEMBAR UJI']);
        $sumoUser(52, 'kembar-uji', 'NAMA KEMBAR UJI');

        // Satu kata terlalu umum untuk digabung.
        User::factory()->create(['name' => 'TUNGGALUJI']);
        $sumoUser(53, 'tunggal-uji', 'TUNGGALUJI');

        // Config bisa memaksa akun baru walau namanya sama.
        User::factory()->create(['name' => 'PAKSA BARU UJI']);
        $sumoUser(54, 'paksa-uji', 'PAKSA BARU UJI');
        config(['sumo-import.user_links' => [$b + 54 => false]]);

        $usersBefore = User::count();
        $report = (new SumoImporter)->run();

        $employee->refresh();
        $this->assertSame($b + 50, $employee->legacy_sumo_user_id);
        $this->assertSame('PEGAWAI GABUNG UJI', $employee->name);
        $this->assertSame('pegawai-uji', $employee->username);
        $this->assertTrue(Hash::check('rahasia-sumo', $employee->password));
        $this->assertSame($this->entity->id, $employee->business_entity_id);
        $this->assertTrue($employee->hasRole('executor'));
        $this->assertTrue($employee->access_all_business_entities);

        $gaisAdmin->refresh();
        $this->assertSame($b + 51, $gaisAdmin->legacy_sumo_user_id);
        $this->assertFalse(Hash::check('rahasia-sumo', $gaisAdmin->password));
        $this->assertFalse($gaisAdmin->access_all_business_entities, 'Akses akun GAIS yang sudah dipakai tidak diperluas diam-diam.');
        $this->assertTrue($gaisAdmin->hasRole('pengajuan_admin'));

        foreach ([52, 53, 54] as $id) {
            $this->assertSame(1, User::where('legacy_sumo_user_id', $b + $id)->whereNotNull('username')->count());
        }

        $this->assertSame(1, $report->count('users.linked_by_name'));
        $this->assertSame(1, $report->count('users.linked_by_username'));
        $this->assertSame(1, $report->count('users.login_filled'));
        $this->assertNotEmpty(array_filter($report->warnings(), fn (string $warning): bool => str_contains($warning, 'NAMA KEMBAR UJI')));
        $this->assertNotEmpty(array_filter($report->notes(), fn (string $note): bool => str_contains($note, 'PT Uji Sumo')));

        // 6 user fixture lama + 3 akun baru (kembar, tunggal, paksa); dua orang digabung.
        $this->assertSame($usersBefore + 9, User::count());
    }

    public function test_dry_run_reports_without_saving(): void
    {
        $usersBefore = User::count();

        $this->artisan('sumo:import', ['--dry-run' => true])
            ->expectsOutputToContain('Uji coba selesai')
            ->assertSuccessful();

        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, ItemRequest::whereKey(self::B + 10)->count());
    }

    private function seedLegacy(): void
    {
        $b = self::B;
        $f = SumoLegacyFixture::class;
        $f::insert('areas', ['id' => $b + 2, 'area' => 'HO-UJI']);
        $f::insert('areas', ['id' => $b + 4, 'area' => 'TOP-UJI']);
        $f::insert('divisions', ['id' => $b + 6, 'division' => 'HCM-UJI', 'area_id' => $b + 2]);
        $f::insert('divisions', ['id' => $b + 9, 'division' => 'WHM-UJI', 'area_id' => $b + 2]);
        $f::insert('divisions', ['id' => $b + 15, 'division' => 'DEPO-UJI', 'area_id' => $b + 4]);
        $f::insert('badan_usahas', ['id' => $b + 1, 'badan_usaha' => '-']);
        $f::insert('badan_usahas', ['id' => $b + 2, 'badan_usaha' => 'PT UJI']);

        $hash = Hash::make('rahasia', ['rounds' => 4]);
        $f::insert('users', ['id' => $b + 1, 'username' => 'admin-uji', 'fullname' => 'ADMIN UJI', 'password' => $hash, 'badan_usaha_id' => $b + 1, 'division_id' => $b + 6, 'role_id' => 1, 'approval_id' => 0]);
        $f::insert('users', ['id' => $b + 2, 'username' => 'ga-uji', 'fullname' => 'ADMIN GA UJI', 'password' => $hash, 'badan_usaha_id' => $b + 1, 'division_id' => $b + 6, 'role_id' => 3, 'approval_id' => $b + 1]);
        $f::insert('users', ['id' => $b + 3, 'username' => 'cs-uji', 'fullname' => 'CS-UJI', 'password' => $hash, 'badan_usaha_id' => $b + 2, 'division_id' => $b + 15, 'role_id' => 4, 'approval_id' => $b + 2]);
        $f::insert('users', ['id' => $b + 4, 'username' => 'whm-uji', 'fullname' => 'WHM UJI', 'password' => $hash, 'badan_usaha_id' => $b + 1, 'division_id' => $b + 9, 'role_id' => 3, 'approval_id' => $b + 1]);
        $f::insert('users', ['id' => $b + 6, 'username' => 'staf-hcm-uji', 'fullname' => 'STAF HCM UJI', 'password' => $hash, 'badan_usaha_id' => $b + 1, 'division_id' => $b + 6, 'role_id' => 4, 'approval_id' => $b + 2]);
        $f::insert('users', ['id' => $b + 8, 'username' => 'lama-uji', 'fullname' => 'LAMA TANPA RIWAYAT UJI', 'password' => $hash, 'badan_usaha_id' => $b + 2, 'division_id' => $b + 15, 'role_id' => 4, 'approval_id' => 0, 'deleted_at' => '2024-01-01 00:00:00']);
        $f::insert('users', ['id' => $b + 5, 'username' => 'gone-uji', 'fullname' => 'GONE UJI', 'password' => $hash, 'badan_usaha_id' => $b + 2, 'division_id' => $b + 15, 'role_id' => 4, 'approval_id' => $b + 2, 'deleted_at' => '2025-06-01 00:00:00']);

        $f::insert('categories', ['id' => $b + 2, 'category' => 'ATK-UJI']);
        $f::insert('unit_types', ['id' => $b + 7, 'unit_type' => 'PCS-UJI']);
        $f::insert('products', ['id' => $b + 100, 'product' => 'Pulpen Uji', 'category_id' => $b + 2, 'unit_type_id' => $b + 7, 'price' => 2500]);
        $f::insert('products', ['id' => $b + 101, 'product' => 'Kertas A4 Uji', 'category_id' => $b + 2, 'unit_type_id' => $b + 7, 'price' => 50000]);
        $f::insert('request_type', ['id' => $b + 1, 'request_type' => 'ASSET/NONASSET', 'pic_division_id' => $b + 6]);
        $f::insert('request_type', ['id' => $b + 2, 'request_type' => 'ATK', 'pic_division_id' => $b + 6]);
        $f::insert('request_type', ['id' => $b + 3, 'request_type' => 'NOTA', 'pic_division_id' => $b + 6]);
        $f::insert('request_settings', ['id' => $b + 1, 'request_month' => '2026-09-01 00:00:00', 'open_date' => '2026-08-26 00:00:00', 'closed_date' => '2026-08-28 00:00:00', 'request_detail' => 'Pengajuan ATK Uji']);
        $f::insert('wa_notification_settings', ['id' => 1, 'is_enabled' => true, 'target_phones' => '0812', 'send_time' => '08:00', 'remind_rent' => true, 'rent_days_before' => '30,7', 'remind_insurance' => true, 'insurance_days_before' => '30', 'remind_request_cutoff' => false, 'request_cutoff_days_before' => '3']);

        $f::insert('requests', ['id' => $b + 10, 'request_code' => 'REQ'.($b + 10), 'user_id' => $b + 3, 'date' => '2026-09-02 09:00:00', 'total_cost' => 0, 'status_po' => 1, 'status_client' => 3, 'request_type_id' => $b + 2]);
        $f::insert('requests', ['id' => $b + 11, 'request_code' => 'REQ'.($b + 11), 'user_id' => $b + 999, 'date' => '2026-09-03 09:00:00', 'total_cost' => 0, 'status_po' => 0, 'status_client' => 1, 'request_type_id' => $b + 1]);
        $f::insert('request_details', ['id' => $b + 1, 'request_id' => $b + 10, 'product_id' => $b + 100, 'qty_request' => 2]);
        $f::insert('request_details', ['id' => $b + 2, 'request_id' => $b + 10, 'product_id' => $b + 101, 'qty_request' => 1, 'qty_approved' => 1]);
        $f::insert('request_details', ['id' => $b + 3, 'request_id' => $b + 77, 'product_id' => $b + 101, 'qty_request' => 1]);

        foreach (['ACCOUNTING', 'MANAGER', 'EXECUTOR', 'ENDUSER'] as $i => $type) {
            $f::insert('request_approvals', ['id' => $b + $i + 1, 'request_id' => (string) ($b + 10), 'approval_type' => $type, 'approved_by' => $type === 'EXECUTOR' ? $b + 2 : null, 'approved_at' => $type === 'EXECUTOR' ? '2026-09-02 10:00:00' : null]);
        }

        $f::insert('request_logs', ['id' => $b + 1, 'user_id' => (string) ($b + 1), 'request_id' => (string) ($b + 10), 'activity' => 'ADMIN merubah pemohon']);
        $f::insert('request_logs', ['id' => $b + 2, 'user_id' => (string) ($b + 5), 'request_id' => (string) ($b + 10), 'activity' => 'GONE melihat pengajuan']);

        $f::insert('problem_report_categories', ['id' => $b + 7, 'problem_report_category' => 'UMUM-UJI']);
        $f::insert('problem_report', ['id' => $b + 20, 'problem_report_code' => 'PR'.($b + 20), 'user_id' => $b + 3, 'date' => '2026-09-05 08:00:00', 'description' => 'AC bocor', 'status' => 'CLOSED', 'status_client' => 1, 'closed_by' => $b + 2, 'closed_at' => '2026-09-06 08:00:00', 'pr_category_id' => $b + 7, 'photo_before' => 'Problem_Report_File/a.jpg']);

        $f::insert('insurance_providers', ['id' => $b + 1, 'insurance_provider' => 'ASURANSI UJI']);
        $f::insert('insurance_categories', ['id' => $b + 1, 'insurance_category' => 'GUDANG UJI']);
        $f::insert('insurance_scopes', ['id' => $b + 1, 'insurance_scope' => 'ALL RISK UJI']);
        $f::insert('insurances', ['id' => $b + 30, 'policy_number' => 'POL-UJI', 'insured_name' => 'Gudang Uji', 'stock_inprov_id' => $b + 1, 'insurance_category_id' => $b + 1, 'insurance_scope_id' => $b + 1, 'join_date' => '0000-00-00 00:00:00', 'expired_date' => '2026-12-31 00:00:00', 'user_id' => $b + 2, 'status' => 'BERJALAN', 'stock_worth' => 1000000]);
        $f::insert('insurance_updates', ['id' => $b + 1, 'insurance_id' => $b + 30, 'policy_number' => 'POL-UJI-A', 'join_date' => '2026-12-31 00:00:00', 'expired_date' => '2027-12-31 00:00:00', 'user_id' => $b + 2, 'status' => 'PEMBAHARUAN']);
        $f::insert('rents', ['id' => $b + 40, 'rent_code' => 'RENT-UJI-IMPORT', 'rented_detail' => 'Ruko', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 12000000, 'join_date' => '2026-01-01 00:00:00', 'expired_date' => '2026-12-31 00:00:00', 'user_id' => $b + 2, 'status' => 'BERJALAN']);
        $f::insert('rent_updates', ['id' => $b + 1, 'rent_id' => (string) ($b + 40), 'rent_code' => 'RENTUP-UJI', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'join_date' => '2027-01-01 00:00:00', 'expired_date' => '2027-12-31 00:00:00', 'user_id' => $b + 2, 'status' => 'BERJALAN']);
        $f::insert('wa_notification_logs', ['id' => $b + 1, 'notif_type' => 'TEST', 'phone' => '0812', 'sent_date' => '2026-10-01', 'status' => 'SUCCESS']);
    }
}
