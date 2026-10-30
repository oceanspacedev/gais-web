<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource\Pages\CreateInsurance;
use App\Filament\Resources\InsuranceResource\Pages\ListInsurances;
use App\Filament\Resources\InsuranceResource\Pages\ViewInsurance;
use App\Filament\Resources\InsuranceResource\RelationManagers\UpdatesRelationManager;
use App\Filament\Resources\RentResource\Pages\ListRents;
use App\Models\Insurance;
use App\Models\InsuranceCategory;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScope;
use App\Models\Rent;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContractResourcesTest extends TestCase
{
    use DatabaseTransactions;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->manager = User::factory()->create(['access_all_business_entities' => true]);
        $this->manager->givePermissionTo(array_map(
            fn (string $name) => Permission::findOrCreate($name, 'web'),
            ['view_any_insurance', 'view_insurance', 'create_insurance', 'update_insurance', 'view_any_rent', 'view_rent', 'create_rent', 'update_rent'],
        ));

        $this->actingAs($this->manager);
    }

    public function test_insurance_can_be_created_and_renewed_from_the_panel(): void
    {
        $provider = InsuranceProvider::create(['name' => 'Provider Uji']);
        $category = InsuranceCategory::create(['name' => 'Kategori Uji']);
        $scope = InsuranceScope::create(['name' => 'Cakupan Uji']);

        Livewire::test(CreateInsurance::class)
            ->fillForm([
                'policy_number' => 'POL-UJI-1',
                'insured_name' => 'Gudang Uji',
                // Sumo mewajibkan kategori dan cakupan saat membuat polis.
                'insurance_category_id' => $category->id,
                'insurance_scope_id' => $scope->id,
                'stock_provider_id' => $provider->id,
                'stock_worth' => 150000000,
                'stock_premium' => 450000,
                'join_date' => '2026-01-01',
                'expired_date' => '2026-12-31',
                'status' => ContractStatus::Running->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $insurance = Insurance::query()->where('policy_number', 'POL-UJI-1')->firstOrFail();
        $this->assertSame($this->manager->id, $insurance->user_id);
        $this->assertSame('2026-12-31', $insurance->effectiveExpiredDate()?->toDateString());

        Livewire::test(UpdatesRelationManager::class, ['ownerRecord' => $insurance, 'pageClass' => ViewInsurance::class])
            ->assertOk()
            ->callTableAction('create', data: [
                'policy_number' => 'POL-UJI-1A',
                'status' => ContractStatus::Renewed->value,
                'join_date' => '2027-01-01',
                'expired_date' => '2027-12-31',
                'stock_premium' => 475000,
            ])
            ->assertHasNoTableActionErrors();

        $insurance = $insurance->fresh();
        $this->assertSame(1, $insurance->updates()->count());
        $this->assertSame('2027-12-31', $insurance->effectiveExpiredDate()?->toDateString());
        $this->assertSame(ContractStatus::Renewed, $insurance->effectiveStatus());

        Livewire::test(ListInsurances::class)->assertOk()->searchTable('POL-UJI-1')->assertCanSeeTableRecords([$insurance]);
    }

    public function test_rent_billing_summary_follows_the_legacy_rounding(): void
    {
        $rent = Rent::create([
            'rent_code' => 'RENT-UJI',
            'rented_detail' => 'Ruko',
            'first_party' => 'PT A',
            'second_party' => 'Bpk B',
            'rent_per_year' => 12000000,
            'cvcs_fund' => 20000000,
            'online_fund' => 4000000,
            'join_date' => '2026-01-01',
            'expired_date' => '2027-01-03',
            'status' => ContractStatus::Running->value,
        ]);

        $summary = $rent->billingSummary();

        $this->assertSame(2, $summary['years'], '367 hari dibulatkan ke atas menjadi 2 tahun.');
        $this->assertSame(24000000, $summary['total']);
        $this->assertSame(24000000, $summary['paid']);
        $this->assertSame('LUNAS', $summary['status']);

        $rent->update(['online_fund' => 5000000]);
        $this->assertSame('LEBIH', $rent->fresh()->billingSummary()['status']);

        $rent->update(['online_fund' => 0]);
        $this->assertSame('BELUM LUNAS', $rent->fresh()->billingSummary()['status']);

        Livewire::test(ListRents::class)->assertOk()->searchTable('RENT-UJI')->assertCanSeeTableRecords([$rent]);
    }
}
