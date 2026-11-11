<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Security Checksheet mengikuti OB Checksheet: role yang memegang izin
 * OB (super_admin, admin, general_affair, audit, ...) mendapat izin security
 * yang setara; role baru `security` setara dengan `office_boy`.
 */
return new class extends Migration
{
    private const SUBJECT = 'security::checksheet';

    /** @var list<string> */
    private const PREFIXES = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'restore', 'restore_any', 'force_delete', 'force_delete_any',
        'export', 'view_all', 'update_all', 'delete_all',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PREFIXES as $prefix) {
            Permission::findOrCreate($prefix.'_'.self::SUBJECT, 'web');
        }

        $security = Role::findOrCreate('security', 'web');

        foreach (Role::query()->where('guard_name', 'web')->with('permissions')->get() as $role) {
            $target = $role->name === 'office_boy' ? $security : $role;

            foreach (self::PREFIXES as $prefix) {
                if ($role->permissions->contains('name', "{$prefix}_ob::checksheet")) {
                    $target->givePermissionTo($prefix.'_'.self::SUBJECT);
                }
            }
        }

        // Berlaku juga bila role office_boy belum ada di database ini.
        $security->givePermissionTo(array_map(
            fn (string $prefix): string => $prefix.'_'.self::SUBJECT,
            ['view_any', 'view', 'create'],
        ));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_map(fn (string $prefix): string => $prefix.'_'.self::SUBJECT, self::PREFIXES))
            ->delete();

        Role::query()->where('name', 'security')->where('guard_name', 'web')->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
