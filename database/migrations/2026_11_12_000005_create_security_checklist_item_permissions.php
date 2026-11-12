<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Pertanyaan Security Checksheet diatur admin dan atasan security (pemegang
 * "Edit Data Semua User" security checksheet); petugas security tidak.
 */
return new class extends Migration
{
    private const SUBJECT = 'security::checklist::item';

    /** @var list<string> */
    private const PREFIXES = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'restore', 'restore_any', 'force_delete', 'force_delete_any',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = array_map(
            fn (string $prefix): Permission => Permission::findOrCreate($prefix.'_'.self::SUBJECT, 'web'),
            self::PREFIXES,
        );

        Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($query) => $query
                ->whereIn('name', [config('filament-shield.super_admin.name', 'super_admin'), 'admin'])
                ->orWhereHas('permissions', fn ($query) => $query->where('name', 'update_all_security::checksheet')))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', array_map(fn (string $prefix): string => $prefix.'_'.self::SUBJECT, self::PREFIXES))
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
