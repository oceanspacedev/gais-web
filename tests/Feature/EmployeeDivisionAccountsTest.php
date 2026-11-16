<?php

namespace Tests\Feature;

use App\Exceptions\ItemRequestException;
use App\Models\Division;
use App\Models\ItemRequest;
use App\Models\ItemRequestType;
use App\Models\JobTitle;
use App\Models\ProblemReport;
use App\Models\Product;
use App\Models\User;
use App\Services\ItemRequestWorkflow;
use App\Services\TalentaUserImportService;
use Filament\Facades\Filament;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Akun pribadi karyawan menggantikan akun bersama toko/divisi: pengajuan dan
 * laporan mencatat divisinya, rekan sedivisi melihatnya, kuota dihitung per
 * divisi, akun toko bisa dikunci, dan role mengikuti jabatan.
 */
class EmployeeDivisionAccountsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_division_members_see_the_division_history_and_it_stays_after_a_move(): void
    {
        $store = Division::create(['name' => 'TOKO-uji-akun']);
        $other = Division::create(['name' => 'TOKO-uji-lain']);
        $type = ItemRequestType::create(['name' => 'ATK-uji-akun']);

        $storeAccount = User::factory()->create(['division_id' => $store->id]);
        $employee = User::factory()->create(['division_id' => $store->id]);
        $outsider = User::factory()->create(['division_id' => $other->id]);
        $noDivision = User::factory()->create();

        $request = ItemRequest::create(['user_id' => $storeAccount->id, 'item_request_type_id' => $type->id, 'requested_at' => now()]);
        $report = ProblemReport::create(['user_id' => $storeAccount->id, 'reported_at' => now(), 'photo_before' => 'uji.jpg']);

        $this->assertSame($store->id, $request->division_id);
        $this->assertSame($store->id, $report->division_id);

        $this->assertTrue(ItemRequest::query()->visibleTo($employee)->whereKey($request->id)->exists());
        $this->assertTrue(ProblemReport::query()->visibleTo($employee)->whereKey($report->id)->exists());
        $this->assertTrue($request->isVisibleTo($employee));
        $this->assertTrue($report->isVisibleTo($employee));

        foreach ([$outsider, $noDivision] as $stranger) {
            $this->assertFalse(ItemRequest::query()->visibleTo($stranger)->whereKey($request->id)->exists());
            $this->assertFalse(ProblemReport::query()->visibleTo($stranger)->whereKey($report->id)->exists());
            $this->assertFalse($request->fresh()->isVisibleTo($stranger));
        }

        // Pemohon pindah divisi: riwayatnya tetap milik toko asal.
        $storeAccount->update(['division_id' => $other->id]);

        $this->assertSame($store->id, $request->fresh()->division_id);
        $this->assertTrue(ItemRequest::query()->visibleTo($employee)->whereKey($request->id)->exists());
        $this->assertFalse(ItemRequest::query()->visibleTo($outsider)->whereKey($request->id)->exists());
    }

    public function test_open_request_quota_is_shared_by_the_division(): void
    {
        config(['item-request.max_open_per_type' => 2]);

        $workflow = app(ItemRequestWorkflow::class);
        $store = Division::create(['name' => 'TOKO-uji-kuota']);
        $type = ItemRequestType::create(['name' => 'ATK-uji-kuota', 'requires_products' => true]);
        $product = Product::create(['name' => 'Pulpen-uji-kuota', 'price' => 1000]);
        $data = ['item_request_type_id' => $type->id, 'items' => [['product_id' => $product->id, 'qty_request' => 1]]];

        $first = User::factory()->create(['division_id' => $store->id]);
        $second = User::factory()->create(['division_id' => $store->id]);
        $third = User::factory()->create(['division_id' => $store->id]);
        $elsewhere = User::factory()->create();

        $workflow->create($first, $data);
        $workflow->create($second, $data);

        $this->assertSame(2, $workflow->openCountByType($third, $type->id));
        $this->assertSame(0, $workflow->openCountByType($elsewhere, $type->id));
        $this->assertNotNull($workflow->create($elsewhere, $data)->id);

        $this->expectException(ItemRequestException::class);
        $workflow->create($third, $data);
    }

    public function test_account_with_disabled_login_cannot_enter_the_panel(): void
    {
        $panel = Filament::getPanel('admin');
        $account = User::factory()->create();

        $this->assertTrue($account->canAccessPanel($panel));

        $account->update(['login_disabled' => true]);
        $disabledAt = $account->fresh()->login_disabled_at;

        $this->assertNotNull($disabledAt);
        $this->assertFalse($account->fresh()->canAccessPanel($panel));

        // Menyimpan ulang tidak menggeser waktu penonaktifan.
        $this->travel(1)->hours();
        $account->update(['login_disabled' => true]);
        $this->assertEquals($disabledAt, $account->fresh()->login_disabled_at);

        $account->update(['login_disabled' => false]);
        $this->assertTrue($account->fresh()->canAccessPanel($panel));
    }

    public function test_talenta_organization_fills_an_empty_division_only(): void
    {
        $division = Division::create(['name' => 'DIV-uji-talenta', 'talenta_organization' => 'Organisasi Uji Talenta']);
        $manual = Division::create(['name' => 'TOKO-uji-talenta']);
        $placed = User::factory()->create(['employee_id' => 'UJI.2026.11.02', 'division_id' => $manual->id]);

        $row = fn (string $employeeId, string $name, string $organization): array => [
            'id_employee' => $employeeId,
            'first_name' => $name,
            'last_name' => '',
            'branch' => 'PT Uji Divisi Talenta',
            'organization' => $organization,
            'job' => 'STAFF UJI TALENTA',
        ];

        $result = (new TalentaUserImportService)->importFromContent(json_encode(['data' => [
            $row('UJI.2026.11.01', 'KARYAWAN BARU UJI', 'ORGANISASI  UJI TALENTA'),
            $row('UJI.2026.11.02', 'KARYAWAN TOKO UJI', 'Organisasi Uji Talenta'),
            $row('UJI.2026.11.03', 'KARYAWAN RETAIL UJI', 'Organisasi Tanpa Padanan'),
        ]]));

        $this->assertSame(3, $result['success_count'], implode(' | ', $result['errors']));
        $this->assertSame($division->id, User::query()->where('employee_id', 'UJI.2026.11.01')->value('division_id'));
        $this->assertSame($manual->id, $placed->fresh()->division_id);
        $this->assertNull(User::query()->where('employee_id', 'UJI.2026.11.03')->value('division_id'));
    }

    public function test_role_tidy_migration_follows_job_titles_and_keeps_existing_access(): void
    {
        $security = JobTitle::query()->firstOrCreate(['title' => 'SECURITY']);
        $cook = JobTitle::query()->firstOrCreate(['title' => 'COOK']);

        $guard = User::factory()->create(['employee_id' => 'UJI.2026.11.11', 'job_title_id' => $security->id]);
        $inactive = User::factory()->create(['employee_id' => 'UJI.2026.11.12', 'job_title_id' => $security->id, 'username' => 'uji-nonaktif']);
        $notFromTalenta = User::factory()->create(['job_title_id' => $security->id]);
        $other = User::factory()->create(['employee_id' => 'UJI.2026.11.13', 'job_title_id' => $cook->id]);

        $retired = Role::findOrCreate('ga_cirebon_task', 'web');
        $permission = Permission::findOrCreate('View:UjiRoleRapi', 'web');
        $retired->givePermissionTo($permission);
        $holder = User::factory()->create();
        $holder->assignRole($retired);

        $this->migration()->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertTrue($guard->fresh()->hasRole('security'));
        $this->assertFalse($inactive->fresh()->hasRole('security'));
        $this->assertFalse($notFromTalenta->fresh()->hasRole('security'));
        $this->assertTrue($other->fresh()->roles->isEmpty());

        $this->assertSame(1, DB::table('roles')->whereRaw('LOWER(name) = ?', ['security'])->count());
        $this->assertSame('security', DB::table('roles')->whereRaw('LOWER(name) = ?', ['security'])->value('name'));

        $this->assertFalse(DB::table('roles')->where('name', 'ga_cirebon_task')->exists());
        $this->assertTrue($holder->fresh()->hasDirectPermission('View:UjiRoleRapi'));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_16_300001_tidy_roles_by_job_title.php');
    }
}
