<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staf GA (role general_affair) memegang hak penuh modul pindahan sumo, sama
 * persis dengan ADMIN di sumo lama (role pengajuan_admin di GAIS): Pengajuan
 * Barang, Lapor Gangguan, Asuransi, Perjanjian Sewa, master barang dan tipe
 * pengajuan, area, user dan divisi, pengaturan Notifikasi WA, scan QR, dan
 * widget beranda sumo. Sebelumnya modul itu hanya untuk role sumo, jadi staf GA
 * yang bukan akun sumo tidak melihat menunya sama sekali.
 *
 * Izinnya disalin dari pengajuan_admin saat migrasi berjalan. Badan usaha tetap
 * membatasi data (BusinessEntityRule): seperti ADMIN sumo yang lintas badan
 * usaha, akun GA perlu "Akses semua badan usaha" untuk melihat semuanya.
 */
return new class extends Migration
{
    private const ROLE = 'general_affair';

    private const SOURCE_ROLE = 'pengajuan_admin';

    /**
     * Subjek, halaman, dan widget modul sumo; dipakai down() supaya hanya izin
     * sumo yang dicabut, bukan izin aset GA yang kebetulan juga dimiliki ADMIN.
     */
    private const SUMO_PATTERNS = [
        '*_item::request', '*_item::request::*', '*_problem::report', '*_problem::report::*',
        '*_insurance', '*_insurance::*', '*_vehicle::insurance', '*_rent', '*_product', '*_product::*',
        '*_unit::type', '*_area', '*_division', 'export_user',
        'View:WaNotificationSettings', 'View:ScanItemRequestQr', 'View:ScanProductQr',
        'widget_Sumo*', 'widget_ItemRequest*', 'widget_ProblemReport*', 'widget_OpenProblemReportsTable',
        'widget_PendingItemRequestsTable', 'widget_Insurance*', 'widget_Expiring*', 'widget_Rent*', 'widget_ContractOverviewStats',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $source = $this->role(self::SOURCE_ROLE);
        $target = $this->role(self::ROLE);

        if ($source !== null && $target !== null) {
            $target->givePermissionTo($source->permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $target = $this->role(self::ROLE);

        $target?->revokePermissionTo($target->permissions->filter(
            fn ($permission): bool => Str::is(self::SUMO_PATTERNS, $permission->name),
        ));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function role(string $name): ?Role
    {
        return Role::query()->where('name', $name)->where('guard_name', 'web')->first();
    }
};
