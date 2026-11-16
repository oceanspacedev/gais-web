<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Asuransi Kendaraan. Role yang memegang izin asuransi (bangunan) mendapat
 * izin kendaraan yang setara, jadi siapa pun yang dulu mengelola menu Asuransi
 * tetap bisa mengelola kedua menu setelah dipisah.
 */
return new class extends Migration
{
    private const SUBJECT = 'vehicle::insurance';

    /** @var list<string> */
    private const PREFIXES = [
        'view_any', 'view', 'create', 'update', 'delete', 'delete_any',
        'restore', 'restore_any', 'force_delete', 'force_delete_any',
        'replicate', 'reorder', 'export',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PREFIXES as $prefix) {
            $permission = Permission::findOrCreate($prefix.'_'.self::SUBJECT, 'web');

            Role::query()
                ->where('guard_name', 'web')
                ->where(fn ($query) => $query
                    ->whereIn('name', [config('filament-shield.super_admin.name', 'super_admin'), 'admin'])
                    ->orWhereHas('permissions', fn ($query) => $query->where('name', $prefix.'_insurance')))
                ->get()
                ->each(fn (Role $role) => $role->givePermissionTo($permission));

            // Izin yang diberikan langsung ke user (bukan lewat role) ikut disalin.
            User::query()
                ->whereHas('permissions', fn ($query) => $query->where('name', $prefix.'_insurance'))
                ->get()
                ->each(fn (User $user) => $user->givePermissionTo($permission));
        }

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
