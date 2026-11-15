<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Satu format nama izin untuk semua hal: bawaan Filament Shield v4,
 * `{Aksi}:{Subjek}` dalam PascalCase (config/filament-shield.php).
 *
 *   view_any_asset::transfer   -> ViewAny:AssetTransfer   (resource)
 *   manage_access_user         -> ManageAccess:User       (aksi khusus)
 *   widget_SumoOverviewStats   -> View:SumoOverviewStats  (widget)
 *   page_SettingsHub           -> View:SettingsHub        (halaman, sisa Shield v3)
 *
 * Baris izin diganti namanya DI TEMPAT (id tetap), jadi penugasan ke role
 * (role_has_permissions) dan ke user langsung (model_has_permissions) tidak
 * berubah. Kalau nama tujuan sudah ada (mis. role disimpan lewat menu Role
 * setelah kode baru aktif tapi sebelum migrasi ini jalan), penugasannya
 * digabung ke baris tujuan dan baris lama dihapus.
 *
 * Migrasi ini harus urut SETELAH semua migrasi izin lama, karena migrasi lama
 * masih membuat dan mencari nama format lama.
 */
return new class extends Migration
{
    /**
     * Widget dasbor; dipakai down() untuk membedakan `View:{Widget}` dari
     * `View:{Model}` milik resource.
     *
     * @var list<string>
     */
    private const WIDGETS = [
        'ActiveAssetServicesTable', 'AssetConditionChart', 'AssetIncidentsTable', 'AssetPurchaseTrendChart',
        'AssetTransferTrendChart', 'AssetValueByCategoryChart', 'AssetsByBusinessEntityChart', 'AttentionStats',
        'ContractOverviewStats', 'CustomAssetWidget', 'ExpiringInsurancesTable', 'ExpiringRentsTable',
        'InsuranceCostChart', 'ItemRequestCostByDivisionChart', 'ItemRequestCostChart', 'ItemRequestItemsChart',
        'ItemRequestStageChart', 'ItemRequestTrendChart', 'MasterDataOverview', 'ObChecksheetTrendChart',
        'OpenProblemReportsTable', 'OpenVendorTasksTable', 'OperationsOverviewStats', 'OverlookWidget',
        'PendingItemRequestsTable', 'ProblemReportCategoryChart', 'ProblemReportCountChart',
        'ProblemReportOverviewStats', 'ProblemReportTrendChart', 'RentExpiryChart', 'SumoOverviewStats',
        'VehicleUsageChart', 'VehiclesOutTable', 'VendorTaskCostChart',
    ];

    /**
     * Izin halaman dan izin kustom yang sejak awal sudah berformat v4; down()
     * tidak menyentuhnya.
     *
     * @var list<string>
     */
    private const ALWAYS_V4 = [
        'View:Horizon', 'View:LogViewer', 'Download:LogViewer', 'Delete:LogViewer', 'View:Pulse',
        'Manage:SumoImport', 'View:TechnicalDocs', 'View:ScanItemRequestQr', 'View:ScanProductQr',
        'View:WaNotificationSettings', 'View:SettingsHub', 'View:SiteSettings', 'View:SocialMenuSettings',
        'View:PWASettingsPage', 'View:LocationSettings',
    ];

    public function up(): void
    {
        $this->rename(fn (string $name): ?string => $this->toV4($name));
    }

    /**
     * Mengembalikan nama format lama. Baris `page_*` yang sudah menjadi
     * `View:*` tidak dikembalikan: nama `page_*` tidak pernah dipakai Shield v4.
     */
    public function down(): void
    {
        $this->rename(fn (string $name): ?string => $this->toLegacy($name));
    }

    private function toV4(string $name): ?string
    {
        if (preg_match('/^(?:widget|page)_([A-Za-z0-9]+)$/', $name, $matches) === 1) {
            return 'View:'.$matches[1];
        }

        if (preg_match('/^([a-z]+(?:_[a-z]+)*)_([a-z]+(?:::[a-z]+)*)$/', $name, $matches) !== 1) {
            return null;
        }

        return Str::studly($matches[1]).':'.Str::studly(str_replace('::', '_', $matches[2]));
    }

    private function toLegacy(string $name): ?string
    {
        if (in_array($name, self::ALWAYS_V4, true)
            || preg_match('/^([A-Z][A-Za-z]*):([A-Z][A-Za-z]*)$/', $name, $matches) !== 1) {
            return null;
        }

        if ($matches[1] === 'View' && in_array($matches[2], self::WIDGETS, true)) {
            return 'widget_'.$matches[2];
        }

        return Str::snake($matches[1]).'_'.str_replace('_', '::', Str::snake($matches[2]));
    }

    /**
     * @param  callable(string): ?string  $target
     */
    private function rename(callable $target): void
    {
        $tables = config('permission.table_names');
        $permissionKey = config('permission.column_names.permission_pivot_key') ?? 'permission_id';

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function () use ($tables, $permissionKey, $target): void {
            $permissions = DB::table($tables['permissions'])->orderBy('id')->get(['id', 'name', 'guard_name']);

            // Kolasi MySQL tidak membedakan huruf besar/kecil, jadi indeks pun tidak.
            $key = fn (string $name, string $guard): string => mb_strtolower($name)."\0".$guard;
            $idsByName = [];

            foreach ($permissions as $permission) {
                $idsByName[$key($permission->name, $permission->guard_name)] = $permission->id;
            }

            foreach ($permissions as $permission) {
                $newName = $target($permission->name);

                if ($newName === null || $newName === $permission->name) {
                    continue;
                }

                $existingId = $idsByName[$key($newName, $permission->guard_name)] ?? null;
                unset($idsByName[$key($permission->name, $permission->guard_name)]);

                if ($existingId === null || $existingId === $permission->id) {
                    DB::table($tables['permissions'])->where('id', $permission->id)->update(['name' => $newName]);
                    $idsByName[$key($newName, $permission->guard_name)] = $permission->id;

                    continue;
                }

                foreach ([$tables['role_has_permissions'], $tables['model_has_permissions']] as $pivot) {
                    $rows = DB::table($pivot)->where($permissionKey, $permission->id)->get()
                        ->map(fn (object $row): array => [...(array) $row, $permissionKey => $existingId])
                        ->all();

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($pivot)->insertOrIgnore($chunk);
                    }

                    DB::table($pivot)->where($permissionKey, $permission->id)->delete();
                }

                DB::table($tables['permissions'])->where('id', $permission->id)->delete();
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
