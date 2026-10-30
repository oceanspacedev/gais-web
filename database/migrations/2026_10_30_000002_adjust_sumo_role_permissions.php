<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyamakan hak role sumo dengan aplikasi lama:
 *  - ADMIN sumo mengelola user, divisi, dan badan usaha (atasan pemohon, cakupan
 *    divisi), jadi pengajuan_admin mendapat lihat/buat/ubah user dan divisi serta
 *    lihat badan usaha. Penugasan role tetap lewat izin manage_access_user.
 *  - Beranda sumo (biaya premi, kontrak jatuh tempo) hanya untuk ADMIN dan
 *    executor HCM; widget kontrak dicabut dari role executor dan diberikan ke
 *    executor HCM lewat config/sumo-import.php (division_permissions).
 */
return new class extends Migration
{
    private const ADMIN_PERMISSIONS = [
        'view_any_user', 'view_user', 'create_user', 'update_user',
        'view_any_division', 'view_division', 'create_division', 'update_division',
        'view_any_business::entity', 'view_business::entity',
    ];

    private const CONTRACT_WIDGETS = ['widget_InsuranceCostChart', 'widget_ExpiringInsurancesTable', 'widget_ExpiringRentsTable'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::query()->where('name', 'pengajuan_admin')->where('guard_name', 'web')->first();

        if ($admin !== null) {
            $admin->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), self::ADMIN_PERMISSIONS));
        }

        $executor = Role::query()->where('name', 'executor')->where('guard_name', 'web')->first();

        if ($executor !== null) {
            $executor->revokePermissionTo(Permission::query()->whereIn('name', self::CONTRACT_WIDGETS)->where('guard_name', 'web')->get());
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $admin = Role::query()->where('name', 'pengajuan_admin')->where('guard_name', 'web')->first();
        $admin?->revokePermissionTo(Permission::query()->whereIn('name', self::ADMIN_PERMISSIONS)->where('guard_name', 'web')->get());

        $executor = Role::query()->where('name', 'executor')->where('guard_name', 'web')->first();
        $executor?->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), self::CONTRACT_WIDGETS));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
