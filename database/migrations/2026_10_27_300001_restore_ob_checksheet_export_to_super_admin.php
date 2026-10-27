<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 2026_09_15_000004 memberi export_ob::checksheet ke super_admin, tetapi di data
 * yang berjalan izin itu hilang lagi dari super_admin sehingga Super Admin tidak
 * bisa mengekspor OB Checksheet. Super admin memegang semua izin secara eksplisit
 * (define_via_gate = false), jadi izinnya dikembalikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $role = Role::query()->where('name', 'super_admin')->where('guard_name', 'web')->first();

        if ($role === null) {
            return;
        }

        $role->givePermissionTo(Permission::findOrCreate('export_ob::checksheet', 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Sengaja kosong: mencabutnya lagi hanya mengembalikan data yang salah.
    }
};
