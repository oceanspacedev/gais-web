<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission Shield untuk modul pindahan sumo-web (pengajuan barang, gangguan,
 * asuransi, sewa, master, QR, notifikasi WA, widget beranda) dan empat role
 * pengganti role sumo: pengajuan_admin (ADMIN), accounting, executor, requester (USER).
 */
return new class extends Migration
{
    private const ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

    /** @var array<string, list<string>> subject => aksi tambahan */
    private const SUBJECTS = [
        'area' => [],
        'product::category' => [],
        'unit::type' => [],
        'product' => ['export', 'import'],
        'item::request::type' => [],
        'item::request::setting' => [],
        'item::request' => ['export', 'view_all', 'approve_accounting', 'approve_manager', 'process', 'audit', 'change_applicant'],
        'item::request::log' => [],
        'problem::report::category' => [],
        'problem::report' => ['export', 'view_all', 'process'],
        'insurance::provider' => [],
        'insurance::category' => [],
        'insurance::scope' => [],
        'insurance' => ['export', 'import'],
        'rent' => ['export', 'import'],
    ];

    private const PAGES = ['View:WaNotificationSettings', 'View:ScanItemRequestQr', 'View:ScanProductQr'];

    private const WIDGETS = [
        'widget_SumoOverviewStats',
        'widget_ItemRequestItemsChart',
        'widget_ItemRequestCostChart',
        'widget_ProblemReportCountChart',
        'widget_ProblemReportCategoryChart',
        'widget_InsuranceCostChart',
        'widget_PendingItemRequestsTable',
        'widget_OpenProblemReportsTable',
        'widget_ExpiringInsurancesTable',
        'widget_ExpiringRentsTable',
    ];

    private const ROLES = ['pengajuan_admin', 'accounting', 'executor', 'requester'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $all = $this->allPermissionNames();

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($this->rolePermissions($all) as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->whereIn('name', $this->allPermissionNames())->where('guard_name', 'web')->delete();
        Role::query()->whereIn('name', self::ROLES)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function allPermissionNames(): array
    {
        $names = [];

        foreach (self::SUBJECTS as $subject => $extras) {
            foreach ([...self::ACTIONS, ...$extras] as $action) {
                $names[] = "{$action}_{$subject}";
            }
        }

        return [...$names, ...self::PAGES, ...self::WIDGETS];
    }

    /**
     * @param  list<string>  $all
     * @return array<string, list<string>>
     */
    private function rolePermissions(array $all): array
    {
        $view = fn (string ...$subjects): array => collect($subjects)
            ->flatMap(fn (string $subject): array => ["view_any_{$subject}", "view_{$subject}"])
            ->all();

        $masters = $view('product', 'product::category', 'unit::type', 'item::request::type', 'problem::report::category');

        return [
            config('filament-shield.super_admin.name', 'super_admin') => $all,
            'pengajuan_admin' => $all,
            'accounting' => [
                ...$view('item::request'),
                'approve_accounting_item::request',
                'export_item::request',
                ...$masters,
                'widget_SumoOverviewStats',
                'widget_ItemRequestItemsChart',
                'widget_ItemRequestCostChart',
                'widget_PendingItemRequestsTable',
            ],
            'executor' => [
                ...$view('item::request'),
                'create_item::request',
                'update_item::request',
                'process_item::request',
                'approve_manager_item::request',
                'change_applicant_item::request',
                'export_item::request',
                ...$view('problem::report'),
                'create_problem::report',
                'update_problem::report',
                'process_problem::report',
                'view_all_problem::report',
                'export_problem::report',
                ...$masters,
                'View:ScanItemRequestQr',
                'View:ScanProductQr',
                ...self::WIDGETS,
            ],
            'requester' => [
                ...$view('item::request'),
                'create_item::request',
                ...$view('problem::report'),
                'create_problem::report',
                ...$view('product'),
                'widget_SumoOverviewStats',
            ],
        ];
    }
};
