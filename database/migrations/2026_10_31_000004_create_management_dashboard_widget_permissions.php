<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin widget dasbor manajemen (satu `widget_{Kelas}` per widget, pola Shield)
 * dan pembagiannya ke role:
 *  - super_admin dan pengajuan_admin: semua widget;
 *  - role yang sudah memegang widget aset lama (widget_CustomAssetWidget, mis.
 *    general_affair dan admin yang dibuat manual) mendapat widget aset,
 *    operasional, antrean, dan data master;
 *  - accounting/executor: widget pengajuan (dan gangguan untuk executor);
 *  - requester: antrean dan ringkasan gangguan miliknya sendiri.
 * Widget Overlook dihapus dari dasbor, jadi izinnya ikut dihapus.
 */
return new class extends Migration
{
    private const ATTENTION = ['widget_AttentionStats'];

    private const ASSETS = [
        'widget_AssetConditionChart', 'widget_AssetValueByCategoryChart', 'widget_AssetPurchaseTrendChart',
        'widget_AssetTransferTrendChart', 'widget_AssetsByBusinessEntityChart', 'widget_AssetIncidentsTable',
        'widget_ActiveAssetServicesTable',
    ];

    private const ITEM_REQUESTS = ['widget_ItemRequestTrendChart', 'widget_ItemRequestStageChart', 'widget_ItemRequestCostByDivisionChart'];

    private const PROBLEM_REPORTS = ['widget_ProblemReportOverviewStats', 'widget_ProblemReportTrendChart'];

    private const CONTRACTS = ['widget_ContractOverviewStats', 'widget_RentExpiryChart'];

    private const OPERATIONS = [
        'widget_OperationsOverviewStats', 'widget_ObChecksheetTrendChart', 'widget_VehicleUsageChart',
        'widget_VendorTaskCostChart', 'widget_OpenVendorTasksTable', 'widget_VehiclesOutTable',
    ];

    private const MASTER = ['widget_MasterDataOverview'];

    private const REMOVED = ['widget_OverlookWidget'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = $this->all();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $grant = function (string $role, array $permissions): void {
            Role::query()->where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permissions);
        };

        $grant(config('filament-shield.super_admin.name', 'super_admin'), $all);
        $grant('pengajuan_admin', $all);
        $grant('accounting', [...self::ATTENTION, ...self::ITEM_REQUESTS]);
        $grant('executor', [...self::ATTENTION, ...self::ITEM_REQUESTS, ...self::PROBLEM_REPORTS]);
        $grant('requester', [...self::ATTENTION, ...self::PROBLEM_REPORTS]);

        $assetWidget = Permission::query()->where('name', 'widget_CustomAssetWidget')->where('guard_name', 'web')->first();

        foreach ($assetWidget?->roles ?? [] as $role) {
            $role->givePermissionTo([...self::ATTENTION, ...self::ASSETS, ...self::OPERATIONS, ...self::MASTER]);
        }

        Permission::query()->whereIn('name', self::REMOVED)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->whereIn('name', $this->all())->where('guard_name', 'web')->delete();

        $overlook = Permission::findOrCreate('widget_OverlookWidget', 'web');
        Role::query()->where('name', config('filament-shield.super_admin.name', 'super_admin'))->where('guard_name', 'web')->first()?->givePermissionTo($overlook);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function all(): array
    {
        return [...self::ATTENTION, ...self::ASSETS, ...self::ITEM_REQUESTS, ...self::PROBLEM_REPORTS, ...self::CONTRACTS, ...self::OPERATIONS, ...self::MASTER];
    }
};
