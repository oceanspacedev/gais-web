<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin ekspor Excel daftar user (sumo: /user/export untuk ADMIN dan HCM).
 * Diberikan ke super_admin dan pengajuan_admin; executor HCM mendapatkannya
 * lewat config/sumo-import.php (division_permissions).
 */
return new class extends Migration
{
    private const PERMISSION = 'export_user';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, 'web');

        foreach ([config('filament-shield.super_admin.name', 'super_admin'), 'pengajuan_admin'] as $roleName) {
            Role::query()->where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()->where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
