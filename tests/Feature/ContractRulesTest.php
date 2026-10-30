<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource\Pages\ListInsurances;
use App\Filament\Resources\RentResource\Pages\ListRents;
use App\Filament\Resources\RentResource\Pages\ViewRent;
use App\Models\Insurance;
use App\Models\InsuranceUpdate;
use App\Models\Rent;
use App\Models\User;
use App\Support\ContractDue;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Aturan daftar asuransi dan sewa yang dibawa dari sumo: nilai pembaharuan
 * terakhir, warna jatuh tempo, selisih stok, tagihan, kode otomatis, dan filter.
 */
class ContractRulesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $viewer = User::factory()->create(['access_all_business_entities' => true]);
        $viewer->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            ['view_any_insurance', 'view_insurance', 'view_any_rent', 'view_rent'],
        ));

        $this->actingAs($viewer);
    }

    public function test_due_colours_follow_the_sumo_tiers(): void
    {
        $today = Carbon::parse('2026-06-01');

        $this->assertNull(ContractDue::insuranceColor($today->copy()->addDays(31), ContractStatus::Running, $today));
        $this->assertSame('warning', ContractDue::insuranceColor($today->copy()->addDays(30), ContractStatus::Running, $today));
        $this->assertSame('warning', ContractDue::insuranceColor($today->copy()->addDays(15), ContractStatus::Running, $today));
        $this->assertSame('danger', ContractDue::insuranceColor($today->copy()->addDays(14), ContractStatus::Renewed, $today));
        $this->assertSame('danger', ContractDue::insuranceColor($today->copy()->subDays(200), ContractStatus::Running, $today), 'Sudah lewat tetap merah.');
        $this->assertSame('gray', ContractDue::insuranceColor($today->copy()->addDays(3), ContractStatus::Closed, $today));
        $this->assertNull(ContractDue::insuranceColor($today->copy()->addDays(200), ContractStatus::Running, $today), 'Tanggal jauh di depan tidak diwarnai.');

        // Sewa: reminder = berakhir - 3 bulan = 1 Sep; kuning 30 hari sebelumnya, merah sejak 1 Sep.
        $expiry = Carbon::parse('2026-12-01');
        $this->assertSame('2026-09-01', ContractDue::reminderDate($expiry, '3')?->toDateString());
        $this->assertNull(ContractDue::rentColor($expiry, 3, ContractStatus::Running, Carbon::parse('2026-07-31')));
        $this->assertSame('warning', ContractDue::rentColor($expiry, 3, ContractStatus::Running, Carbon::parse('2026-08-02')));
        $this->assertSame('danger', ContractDue::rentColor($expiry, 3, ContractStatus::Running, Carbon::parse('2026-09-01')));
        $this->assertSame('gray', ContractDue::rentColor($expiry, 3, ContractStatus::Closed, Carbon::parse('2026-10-01')));
        $this->assertSame('danger', ContractDue::rentColor($expiry, null, ContractStatus::Running, Carbon::parse('2026-12-01')), 'Tanpa bulan pengingat, tanggal reminder = tanggal berakhir.');
    }

    public function test_insurance_stock_status_and_effective_values_come_from_the_latest_renewal(): void
    {
        $insurance = Insurance::create([
            'policy_number' => 'POL-RULE-1', 'insured_name' => 'Gudang Rule', 'stock_worth' => 1000, 'actual_stock_worth' => 1250,
            'join_date' => '2025-01-01', 'expired_date' => '2025-12-31', 'status' => ContractStatus::Renewed->value,
        ]);

        $this->assertSame(-250, $insurance->stockDifference());
        $this->assertSame('OK', $insurance->stockStatus(), 'Aktual 25% di atas nilai stok masih OK.');

        $insurance->updates()->create([
            'policy_number' => 'POL-RULE-1A', 'stock_worth' => 1000, 'actual_stock_worth' => 1300,
            'join_date' => '2026-01-01', 'expired_date' => '2026-12-31', 'status' => ContractStatus::Running->value,
        ]);
        $insurance = $insurance->fresh();

        $this->assertSame('POL-RULE-1A', $insurance->effective('policy_number'));
        $this->assertSame('Overlimit', $insurance->effectiveRow()->stockStatus());
        $this->assertSame(-300, $insurance->effectiveRow()->stockDifference());
        $this->assertSame(ContractStatus::Running, $insurance->effectiveStatus());

        $under = new InsuranceUpdate(['stock_worth' => 1000, 'actual_stock_worth' => 650]);
        $this->assertSame('Underlimit', $under->stockStatus());
        $this->assertNull((new InsuranceUpdate)->stockStatus());
    }

    public function test_rent_codes_are_generated_and_billing_follows_the_latest_renewal(): void
    {
        $rent = Rent::create([
            'rented_detail' => 'Ruko Rule', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 12000000,
            'cvcs_fund' => 12000000, 'join_date' => '2025-01-01', 'expired_date' => '2025-12-31', 'month_before_reminder' => '2',
        ]);

        $this->assertMatchesRegularExpression('/^RENT\d{4}$/', $rent->rent_code);
        $this->assertSame('LUNAS', $rent->billingSummary()['status']);
        $this->assertSame('2025-10-31', $rent->reminderDate()?->toDateString());

        $update = $rent->updates()->create([
            'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 15000000, 'cvcs_fund' => 5000000,
            'join_date' => '2026-01-01', 'expired_date' => '2027-01-03', 'month_before_reminder' => '1',
        ]);
        $rent = $rent->fresh();

        $this->assertMatchesRegularExpression('/^RENTUP\d{4}$/', $update->rent_code);
        $this->assertSame($update->rent_code, $rent->effective('rent_code'));

        $billing = $rent->effectiveBillingSummary();
        $this->assertSame(2, $billing['years'], '367 hari dibulatkan ke atas menjadi 2 tahun.');
        $this->assertSame(30000000, $billing['total']);
        $this->assertSame(25000000, $billing['remaining']);
        $this->assertSame('BELUM LUNAS', $billing['status']);
        $this->assertSame('2026-12-03', $rent->effectiveReminderDate()?->toDateString());

        $zero = new Rent(['join_date' => '2026-01-01', 'expired_date' => '2026-01-01', 'rent_per_year' => 100, 'cvcs_fund' => 0]);
        $this->assertSame(0, $zero->billingSummary()['total'], 'Kontrak nol hari tidak ditagih (sumo).');
    }

    public function test_lists_hide_ended_contracts_by_default_and_due_filter_uses_the_effective_expiry(): void
    {
        // Database pengembangan bisa berisi data impor sumo; prefix unik + pencarian
        // menjaga record uji tetap di halaman pertama tabel.
        $running = $this->insurance('UJIRULE-RUN', '2027-06-30');
        $closed = $this->insurance('UJIRULE-CLOSED', '2027-06-30', ContractStatus::Closed);
        $closedByRenewal = $this->insurance('UJIRULE-CLOSED-UP', '2027-06-30');
        $closedByRenewal->updates()->create(['policy_number' => 'UJIRULE-CLOSED-UP-A', 'join_date' => '2027-07-01', 'expired_date' => '2028-06-30', 'status' => ContractStatus::Closed->value]);

        // Induk sudah lewat tetapi diperbarui jauh ke depan: tidak jatuh tempo.
        $renewedFar = $this->insurance('UJIRULE-FAR', today()->subMonths(6)->toDateString(), ContractStatus::Renewed);
        $renewedFar->updates()->create(['policy_number' => 'UJIRULE-FAR-A', 'join_date' => today()->subMonths(6)->toDateString(), 'expired_date' => today()->addYear()->toDateString(), 'status' => ContractStatus::Running->value]);

        // Induk jauh ke depan? Tidak: induk lama, pembaharuan terakhir 10 hari lagi.
        $dueSoon = $this->insurance('UJIRULE-SOON', today()->subYear()->toDateString(), ContractStatus::Renewed);
        $dueSoon->updates()->create(['policy_number' => 'UJIRULE-SOON-A', 'join_date' => today()->subYear()->toDateString(), 'expired_date' => today()->addDays(10)->toDateString(), 'status' => ContractStatus::Running->value]);

        Livewire::test(ListInsurances::class)
            ->assertOk()
            ->searchTable('UJIRULE')
            ->assertCanSeeTableRecords([$running, $renewedFar, $dueSoon])
            ->assertCanNotSeeTableRecords([$closed, $closedByRenewal])
            ->filterTable('due_soon')
            ->assertCanSeeTableRecords([$dueSoon])
            ->assertCanNotSeeTableRecords([$running, $renewedFar])
            ->removeTableFilter('due_soon')
            ->removeTableFilter('active')
            ->assertCanSeeTableRecords([$running, $closed, $closedByRenewal, $renewedFar, $dueSoon])
            ->filterTable('status', ContractStatus::Closed->value)
            ->assertCanSeeTableRecords([$closed, $closedByRenewal], 'Filter status cocok dengan status induk atau pembaharuan.')
            ->assertCanNotSeeTableRecords([$running])
            ->removeTableFilter('status')
            ->searchTable('UJIRULE-SOON-A')
            ->assertCanSeeTableRecords([$dueSoon], 'Nomor polis pembaharuan ikut dicari.')
            ->assertCanNotSeeTableRecords([$running]);

        $rent = Rent::create(['rented_detail' => 'UJIRULE Ruko List', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 1, 'join_date' => '2026-01-01', 'expired_date' => '2027-12-31']);
        $ended = Rent::create(['rented_detail' => 'UJIRULE Ruko Tutup', 'first_party' => 'PT A', 'second_party' => 'Bpk B', 'rent_per_year' => 1, 'join_date' => '2026-01-01', 'expired_date' => '2027-12-31', 'status' => ContractStatus::Closed->value]);

        Livewire::test(ListRents::class)
            ->assertOk()
            ->searchTable('UJIRULE')
            ->assertCanSeeTableRecords([$rent])
            ->assertCanNotSeeTableRecords([$ended]);

        // Halaman detail sewa menampilkan ringkasan tagihan (sumo: rents/show).
        Livewire::test(ViewRent::class, ['record' => $rent->getRouteKey()])
            ->assertOk()
            ->assertSee('Total Tagihan')
            ->assertSee('BELUM LUNAS');
    }

    private function insurance(string $policy, string $expired, ContractStatus $status = ContractStatus::Running): Insurance
    {
        return Insurance::create([
            'policy_number' => $policy, 'insured_name' => 'Tertanggung '.$policy,
            'join_date' => '2026-01-01', 'expired_date' => $expired, 'status' => $status->value,
        ]);
    }
}
