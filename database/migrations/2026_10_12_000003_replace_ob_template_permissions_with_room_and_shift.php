<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Template Tugas OB diganti Master Ruangan dan Master Shift. Role yang dulu
 * boleh mengelola template mendapat izin yang sama untuk kedua master baru.
 */
return new class extends Migration
{
    private const SUPER_ADMIN = 'super_admin';

    private const ACTIONS = ['view_any', 'view', 'create', 'update', 'delete', 'delete_any'];

    private const OLD_SUBJECT = 'ob::task::template';

    private const NEW_SUBJECTS = ['ob::room', 'ob::shift'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where(fn ($query) => $query
                ->whereIn('name', [config('filament-shield.super_admin.name', self::SUPER_ADMIN), 'admin'])
                ->orWhereHas('permissions', fn ($query) => $query->whereIn('name', $this->names(self::OLD_SUBJECT))))
            ->with('permissions')
            ->get();

        foreach (self::NEW_SUBJECTS as $subject) {
            foreach (self::ACTIONS as $action) {
                $permission = Permission::findOrCreate("{$action}_{$subject}", 'web');

                // Role yang hanya boleh melihat template cukup mendapat izin lihat.
                foreach ($roles as $role) {
                    if ($this->roleHad($role, $action)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }

        Permission::query()
            ->whereIn('name', $this->names(self::OLD_SUBJECT))
            ->where('guard_name', 'web')
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ACTIONS as $action) {
            Permission::findOrCreate("{$action}_".self::OLD_SUBJECT, 'web');
        }

        foreach (self::NEW_SUBJECTS as $subject) {
            Permission::query()
                ->whereIn('name', $this->names($subject))
                ->where('guard_name', 'web')
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function roleHad(Role $role, string $action): bool
    {
        if (in_array($role->name, [config('filament-shield.super_admin.name', self::SUPER_ADMIN), 'admin'], true)) {
            return true;
        }

        return $role->permissions->contains('name', "{$action}_".self::OLD_SUBJECT);
    }

    /**
     * @return list<string>
     */
    private function names(string $subject): array
    {
        return array_map(fn (string $action): string => "{$action}_{$subject}", self::ACTIONS);
    }
};
