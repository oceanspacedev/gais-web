<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role general_affair (staf GA dari Shelf) belum pernah mendapat izin Pengajuan
 * Barang: modul itu masuk dari sumo dengan role sendiri (pengajuan_admin,
 * accounting, executor, requester), jadi staf GA yang bukan akun sumo tidak
 * melihat menunya sama sekali. Di sumo GA bekerja sebagai executor divisi HCM
 * yang cakupannya semua pengajuan, jadi general_affair mendapat hak executor
 * ditambah "Lihat Data Semua User". Badan usaha tetap membatasi (BusinessEntityRule):
 * staf tanpa akses semua badan usaha hanya melihat pengajuan badan usahanya.
 */
return new class extends Migration
{
    private const ROLE = 'general_affair';

    private const PERMISSIONS = [
        'view_any_item::request',
        'view_item::request',
        'create_item::request',
        'update_item::request',
        'export_item::request',
        'view_all_item::request',
        'process_item::request',
        'approve_manager_item::request',
        'change_applicant_item::request',
        'View:ScanItemRequestQr',
        'widget_PendingItemRequestsTable',
        'widget_ItemRequestTrendChart',
        'widget_ItemRequestStageChart',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::query()->where('name', self::ROLE)->where('guard_name', 'web')->first();

        $role?->givePermissionTo(array_map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'), self::PERMISSIONS));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::query()->where('name', self::ROLE)->where('guard_name', 'web')->first()
            ?->revokePermissionTo(Permission::query()->whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->get());

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
