<?php

namespace Tests\Feature;

use App\Filament\Widgets\ExpiringInsurancesTable;
use App\Filament\Widgets\ExpiringRentsTable;
use App\Filament\Widgets\InsuranceCostChart;
use App\Filament\Widgets\ItemRequestCostChart;
use App\Filament\Widgets\ItemRequestItemsChart;
use App\Filament\Widgets\OpenProblemReportsTable;
use App\Filament\Widgets\PendingItemRequestsTable;
use App\Filament\Widgets\ProblemReportCategoryChart;
use App\Filament\Widgets\ProblemReportCountChart;
use App\Filament\Widgets\SumoOverviewStats;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SumoDashboardWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @return list<array{0: class-string}>
     */
    public static function widgets(): array
    {
        return [
            [SumoOverviewStats::class],
            [ItemRequestItemsChart::class],
            [ItemRequestCostChart::class],
            [ProblemReportCountChart::class],
            [ProblemReportCategoryChart::class],
            [InsuranceCostChart::class],
            [PendingItemRequestsTable::class],
            [OpenProblemReportsTable::class],
            [ExpiringInsurancesTable::class],
            [ExpiringRentsTable::class],
        ];
    }

    /**
     * @param  class-string  $widget
     */
    #[DataProvider('widgets')]
    public function test_every_sumo_widget_renders_for_a_super_admin(string $widget): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $admin = User::factory()->create(['access_all_business_entities' => true]);
        $admin->assignRole(Role::findByName(config('filament-shield.super_admin.name', 'super_admin'), 'web'));
        $this->actingAs($admin);

        $this->assertTrue($widget::canView(), "{$widget} should be visible to a super admin.");

        $component = Livewire::test($widget)->assertOk();

        if ($widget === SumoOverviewStats::class) {
            $component->assertSee('Pengajuan Barang')->assertSee('Laporan Gangguan');
        }
    }

    public function test_widgets_stay_hidden_without_their_permission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs(User::factory()->create());

        $this->assertFalse(SumoOverviewStats::canView());
        $this->assertFalse(InsuranceCostChart::canView());
    }
}
