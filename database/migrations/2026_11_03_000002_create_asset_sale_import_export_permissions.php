<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin impor Excel (penjualan massal) dan ekspor riwayat penjualan aset antar
 * badan usaha. Siapa yang boleh mencatat penjualan (create_asset::sale) boleh
 * mengimpornya; siapa yang boleh melihat daftarnya (view_any_asset::sale) boleh
 * mengekspor. super_admin mendapat keduanya.
 */
return new class extends Migration
{
    /** @var array<string, string> izin baru => izin penjualan yang menjadi dasarnya */
    private const PERMISSIONS = [
        'import_asset::sale' => 'create_asset::sale',
        'export_asset::sale' => 'view_any_asset::sale',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (array_keys(self::PERMISSIONS) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->get();
        $usersWithDirectPermissions = User::query()->whereHas('permissions')->with('permissions')->get();

        foreach ([...$roles, ...$usersWithDirectPermissions] as $holder) {
            foreach (self::PERMISSIONS as $permission => $basis) {
                if ($holder->permissions->contains('name', $basis)) {
                    $holder->givePermissionTo($permission);
                }
            }
        }

        $roles->firstWhere('name', config('filament-shield.super_admin.name', 'super_admin'))
            ?->givePermissionTo(array_keys(self::PERMISSIONS));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
