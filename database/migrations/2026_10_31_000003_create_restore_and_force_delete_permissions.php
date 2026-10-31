<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Izin Shield untuk filter Sampah: restore_*, restore_any_*, force_delete_*,
 * force_delete_any_* untuk setiap model fitur yang soft delete (policy-nya
 * sudah memeriksa nama izin ini, tetapi izinnya belum pernah dibuat).
 *  - Role dan user yang boleh menghapus (delete_*, termasuk izin langsung dari
 *    impor sumo) boleh memulihkan.
 *  - Hapus permanen hanya diberikan ke role super admin (di proyek ini super
 *    admin tidak mem-bypass Gate, haknya dari permission); berikan ke role
 *    lain lewat Pengaturan > Roles bila perlu.
 */
return new class extends Migration
{
    private const SUBJECTS = [
        'area', 'asset', 'asset::location', 'asset::service', 'asset::transfer', 'brand',
        'business::entity', 'category', 'custom::asset::attribute', 'division', 'insurance',
        'insurance::category', 'insurance::provider', 'insurance::scope', 'item::request',
        'item::request::log', 'item::request::setting', 'item::request::type', 'job::title',
        'ob::checksheet', 'ob::room', 'ob::shift', 'problem::report', 'problem::report::category',
        'product', 'product::category', 'rent', 'task', 'unit::type', 'vehicle::checksheet', 'vendor',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roles = Role::query()->where('guard_name', 'web')->with('permissions')->get();
        $superAdmin = $roles->firstWhere('name', config('filament-shield.super_admin.name', 'super_admin'));
        $usersWithDirectPermissions = User::query()->whereHas('permissions')->with('permissions')->get();

        foreach (self::SUBJECTS as $subject) {
            foreach (['restore', 'restore_any', 'force_delete', 'force_delete_any'] as $prefix) {
                Permission::findOrCreate("{$prefix}_{$subject}", 'web');
            }

            $superAdmin?->givePermissionTo("force_delete_{$subject}", "force_delete_any_{$subject}");

            foreach ([...$roles, ...$usersWithDirectPermissions] as $holder) {
                if ($holder->permissions->contains('name', "delete_{$subject}")) {
                    $holder->givePermissionTo("restore_{$subject}");
                }

                if ($holder->permissions->contains('name', "delete_any_{$subject}")) {
                    $holder->givePermissionTo("restore_any_{$subject}");
                }
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::SUBJECTS as $subject) {
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', ["restore_{$subject}", "restore_any_{$subject}", "force_delete_{$subject}", "force_delete_any_{$subject}"])
                ->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
