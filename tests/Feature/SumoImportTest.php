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
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\SumoLegacyFixture;
use Tests\TestCase;

class SumoImportTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessEntity $entity;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        SumoLegacyFixture::install();

        $this->entity = BusinessEntity::create(['name' => 'PT Uji Sumo', 'format' => 'UJI']);

        config([
            'sumo-import.business_entities' => [1 => null, 2 => $this->entity->id],
            'sumo-import.scopes' => [6 => [[null, null]], 9 => [[2, 4]]],
            'sumo-import.division_permissions' => ['HCM' => ['view_any_insurance', 'view_insurance', 'import_insurance']],
            'sumo-import.legacy_user_permissions' => [4 => ['export_item::request']],
        ]);

        $this->seedLegacy();
    }

    public function test_import_maps_users_requests_and_contracts_and_is_repeatable(): void
    {
        $report = (new SumoImporter)->run();

        $admin = User::where('legacy_sumo_user_id', 1)->firstOrFail();
        $hcm = User::where('legacy_sumo_user_id', 2)->firstOrFail();
        $requester = User::where('legacy_sumo_user_id', 3)->firstOrFail();
        $warehouse = User::where('legacy_sumo_user_id', 4)->firstOrFail();
        $gone = User::where('legacy_sumo_user_id', 5)->firstOrFail();

        $this->assertTrue($admin->hasRole('pengajuan_admin'));
        $this->assertTrue($admin->access_all_business_entities);
        $this->assertTrue($hcm->hasRole('executor'));
        $this->assertTrue($hcm->can('import_insurance'));
        $this->assertSame(6, $hcm->division_id);
        $this->assertTrue($requester->hasRole('requester'));
        $this->assertFalse($requester->access_all_business_entities);
        $this->assertSame($this->entity->id, $requester->business_entity_id);
        $this->assertSame($hcm->id, $requester->approver_user_id);
        $this->assertTrue(Hash::check('rahasia', $requester->password));
        $this->assertTrue($warehouse->can('export_item::request'));
        $this->assertSame('gone-nonaktif-sumo5', $gone->username);
        $this->assertCount(0, $gone->roles);

        $request = ItemRequest::with('details', 'approvals', 'logs')->findOrFail(10);
        $this->assertSame('REQ10', $request->request_code);
        $this->assertSame($requester->id, $request->user_id);
        $this->assertSame($this->entity->id, $request->business_entity_id);
        $this->assertSame(2, $request->details->count());
        $this->assertSame(4, $request->approvals->count());
        $this->assertSame($hcm->id, $request->approvals->firstWhere('approval_type', 'EXECUTOR')->approved_by_user_id);
        $this->assertSame(3, $request->client_status->value);
        $this->assertTrue($request->po_status);
        $this->assertSame($admin->id, $request->logs->first()->user_id);
        $this->assertSame(1, $report->count('item_request_details.orphan_skipped'));

        $this->assertNull(ItemRequest::findOrFail(11)->user_id, 'Pengajuan dengan pemohon hilang tetap diimpor tanpa user.');

        $problem = ProblemReport::findOrFail(20);
        $this->assertSame('PR20', $problem->code);
        $this->assertSame($requester->id, $problem->user_id);
        $this->assertSame('CLOSED', $problem->status->value);
        $this->assertSame(7, $problem->problem_report_category_id);

        $this->assertSame(1, DB::table('insurances')->where('id', 30)->where('status', 'BERJALAN')->count());
        $this->assertNull(DB::table('insurances')->where('id', 30)->value('join_date'), 'Tanggal 0000-00-00 menjadi null.');
        $this->assertSame(1, DB::table('insurance_updates')->where('insurance_id', 30)->count());
        $this->assertSame(1, DB::table('rents')->where('id', 40)->count());
        $this->assertSame(1, DB::table('rent_updates')->where('rent_id', 40)->count());
        $this->assertSame('Pengajuan ATK Bulanan', DB::table('item_request_settings')->value('request_detail'));
        $this->assertEquals(1, DB::table('item_request_types')->where('id', 1)->value('requires_accounting_approval'));
        $this->assertEquals(1, DB::table('item_request_types')->where('id', 2)->value('requires_products'));
        $this->assertSame(2, ItemRequestScope::count());

        $usersBefore = User::count();
        (new SumoImporter)->run();

        $this->assertSame($usersBefore, User::count(), 'Impor ulang tidak membuat user ganda.');
        $this->assertSame(2, ItemRequest::findOrFail(10)->details()->count());
        $this->assertSame(4, ItemRequest::findOrFail(10)->approvals()->count());
        $this->assertSame(2, ItemRequestScope::count());
    }

    public function test_dry_run_reports_without_saving(): void
    {
        $usersBefore = User::count();

        $this->artisan('sumo:import', ['--dry-run' => true])
            ->expectsOutputToContain('Uji coba selesai')
            ->assertSuccessful();

        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, ItemRequest::whereKey(10)->count());
    }

    private function seedLegacy(): void
    {
        $f = SumoLegacyFixture::class;
        $f::insert('areas', ['id' => 2, 'area' => 'HO']);
        $f::insert('areas', ['id' => 4, 'area' => 'TOP']);
        $f::insert('divisions', ['id' => 6, 'division' => 'HCM', 'area_id' => 2]);
        $f::insert('divisions', ['id' => 9, 'division' => 'WHM', 'area_id' => 2]);
        $f::insert('divisions', ['id' => 15, 'division' => 'DEPO-CRB', 'area_id' => 4]);
        $f::insert('badan_usahas', ['id' => 1, 'badan_usaha' => '-']);
        $f::insert('badan_usahas', ['id' => 2, 'badan_usaha' => 'PT UJI']);

        $hash = Hash::make('rahasia', ['rounds' => 4]);
        $f::insert('users', ['id' => 1, 'username' => 'admin-sumo', 'fullname' => 'ADMIN', 'password' => $hash, 'badan_usaha_id' => 1, 'division_id' => 6, 'role_id' => 1, 'approval_id' => 0]);
        $f::insert('users', ['id' => 2, 'username' => 'ga-sumo', 'fullname' => 'ADMIN GA', 'password' => $hash, 'badan_usaha_id' => 1, 'division_id' => 6, 'role_id' => 3, 'approval_id' => 1]);
        $f::insert('users', ['id' => 3, 'username' => 'cs-uji', 'fullname' => 'CS-UJI', 'password' => $hash, 'badan_usaha_id' => 2, 'division_id' => 15, 'role_id' => 4, 'approval_id' => 2]);
        $f::insert('users', ['id' => 4, 'username' => 'whm-sumo', 'fullname' => 'WHM', 'password' => $hash, 'badan_usaha_id' => 1, 'division_id' => 9, 'role_id' => 3, 'approval_id' => 1]);
        $f::insert('users', ['id' => 5, 'username' => 'gone', 'fullname' => 'GONE', 'password' => $hash, 'badan_usaha_id' => 2, 'division_id' => 15, 'role_id' => 4, 'approval_id' => 2, 'deleted_at' => '2025-06-01 00:00:00']);

        $f::insert('categories', ['id' => 2, 'category' => 'ATK']);
        $f::insert('unit_types', ['id' => 7, 'unit_type' => 'PCS']);
        $f::insert('products', ['id' => 100, 'product' => 'Pulpen', 'category_id' => 2, 'unit_type_id' => 7, 'price' => 2500]);
        $f::insert('products', ['id' => 101, 'product' => 'Kertas A4', 'category_id' => 2, 'unit_type_id' => 7, 'price' => 50000]);
        $f::insert('request_type', ['id' => 1, 'request_type' => 'ASSET/NONASSET', 'pic_division_id' => 6]);
        $f::insert('request_type', ['id' => 2, 'request_type' => 'ATK', 'pic_division_id' => 6]);
        $f::insert('request_type', ['id' => 3, 'request_type' => 'NOTA', 'pic_division_id' => 6]);
        $f::insert('request_settings', ['id' => 1, 'request_month' => '2026-09-01 00:00:00', 'open_date' => '2026-08-26 00:00:00', 'closed_date' => '2026-08-28 00:00:00', 'request_detail' => 'Pengajuan ATK Bulanan']);
        $f::insert('wa_notification_settings', ['id' => 1, 'is_enabled' => true, 'target_phones' => '0812', 'send_time' => '08:00', 'remind_rent' => true, 'rent_days_before' => '30,7', 'remind_insurance' => true, 'insurance_days_before' => '30', 'remind_request_cutoff' => false, 'request_cutoff_days_before' => '3']);

        $f::insert('requests', ['id' => 10, 'request_code' => 'REQ10', 'user_id' => 3, 'date' => '2026-09-02 09:00:00', 'total_cost' => 0, 'status_po' => 1, 'status_client' => 3, 'request_type_id' => 2]);
        $f::insert('requests', ['id' => 11, 'request_code' => 'REQ11', 'user_id' => 999, 'date' => '2026-09-03 09:00:00', 'total_cost' => 0, 'status_po' => 0, 'status_client' => 1, 'request_type_id' => 1]);
        $f::insert('request_details', ['id' => 1, 'request_id' => 10, 'product_id' => 100, 'qty_request' => 2]);
        $f::insert('request_details', ['id' => 2, 'request_id' => 10, 'product_id' => 101, 'qty_request' => 1, 'qty_approved' => 1]);
        $f::insert('request_details', ['id' => 3, 'request_id' => 77, 'product_id' => 101, 'qty_request' => 1]);

        foreach (['ACCOUNTING', 'MANAGER', 'EXECUTOR', 'ENDUSER'] as $i => $type) {
            $f::insert('request_approvals', ['id' => $i + 1, 'request_id' => '10', 'approval_type' => $type, 'approved_by' => $type === 'EXECUTOR' ? 2 : null, 'approved_at' => $type === 'EXECUTOR' ? '2026-09-02 10:00:00' : null]);
        }

        $f::insert('request_logs', ['id' => 1, 'user_id' => '1', 'request_id' => '10', 'activity' => 'ADMIN merubah pemohon']);

        $f::insert('problem_report_categories', ['id' => 7, 'problem_report_category' => 'UMUM']);
        $f::insert('problem_report', ['id' => 20, 'problem_report_code' => 'PR20', 'user_id' => 3, 'date' => '2026-09-05 08:00:00', 'description' => 'AC bocor', 'status' => 'CLOSED', 'status_client' => 1, 'closed_by' => 2, 'closed_at' => '2026-09-06 08:00:00', 'pr_category_id' => 7, 'photo_before' => 'Problem_Report_File/a.jpg']);

        $f::insert('insurance_providers', ['id' => 1, 'insurance_provider' => 'ASURANSI UJI']);
        $f::insert('insurance_categories', ['id' => 1, 'insurance_category' => 'GUDANG']);
        $f::insert('insurance_scopes', ['id' => 1, 'insurance_scope' => 'ALL RISK']);
        $f::insert('insurances', ['id' => 30, 'policy_number' => 'POL-1', 'insured_name' => 'Gudang Uji', 'stock_inprov_id' => 1, 'insurance_category_id' => 1, 'insurance_scope_id' => 1, 'join_date' => '0000-00-00 00:00:00', 'expired_date' => '2026-12-31 00:00:00', 'user_id' => 2, 'status' => 'BERJALAN', 'stock_worth' => 1000000]);
        $f::insert('insurance_updates', ['id' => 1, 'insurance_id' => 30, 'policy_number' => 'POL-1A', 'join_date' => '2026-12-31 00:00:00', 'expired_date' => '2027-12-31 00:00:00', 'user_id' => 2, 'status' => 'PEMBAHARUAN']);
        $f::insert('rents', ['id' => 40, 'rent_code' => 'RENT40', 'rented_detail' => 'Ruko', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 12000000, 'join_date' => '2026-01-01 00:00:00', 'expired_date' => '2026-12-31 00:00:00', 'user_id' => 2, 'status' => 'BERJALAN']);
        $f::insert('rent_updates', ['id' => 1, 'rent_id' => '40', 'rent_code' => 'RENTUP1', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'join_date' => '2027-01-01 00:00:00', 'expired_date' => '2027-12-31 00:00:00', 'user_id' => 2, 'status' => 'BERJALAN']);
        $f::insert('wa_notification_logs', ['id' => 1, 'notif_type' => 'TEST', 'phone' => '0812', 'sent_date' => '2026-10-01', 'status' => 'SUCCESS']);
    }
}
