<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ADMIN di sumo lama adalah staf GA, jadi tidak perlu role terpisah: user ber-role
 * pengajuan_admin dipindah ke general_affair (yang sudah memegang semua izinnya,
 * lihat migrasi 2026_11_16_000001), lalu role pengajuan_admin dihapus. Impor sumo
 * berikutnya memetakan ADMIN langsung ke general_affair (config/sumo-import.php).
 */
return new class extends Migration
{
    private const OLD_ROLE = 'pengajuan_admin';

    private const NEW_ROLE = 'general_affair';

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $old = Role::query()->where('name', self::OLD_ROLE)->where('guard_name', 'web')->first();

        if ($old === null) {
            return;
        }

        $new = Role::findOrCreate(self::NEW_ROLE, 'web');
        $new->givePermissionTo($old->permissions);

        $table = config('permission.table_names.model_has_roles') ?: 'model_has_roles';
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        foreach (DB::table($table)->where($roleKey, $old->id)->get() as $assignment) {
            DB::table($table)->insertOrIgnore([...(array) $assignment, $roleKey => $new->id]);
        }

        $old->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Role dibuat lagi dengan izin general_affair, tanpa user:
        // siapa yang dulu pengajuan_admin tidak tercatat setelah digabung.
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $new = Role::query()->where('name', self::NEW_ROLE)->where('guard_name', 'web')->first();

        Role::findOrCreate(self::OLD_ROLE, 'web')->syncPermissions($new?->permissions ?? []);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
