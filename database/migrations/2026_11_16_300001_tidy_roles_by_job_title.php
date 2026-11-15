<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Merapikan role supaya sejalan dengan jabatan di bawah HCM (General Affair,
 * Security, Office Boy), tanpa menambah role baru:
 *
 *  - role "Security" (dibuat manual, huruf besar) disamakan dengan "security"
 *    yang dipakai kode (migrasi 2026_11_12_000003);
 *  - role "ga_cirebon_task" (dibuat manual, tidak dikenal kode) dihapus; izinnya
 *    dipindah menjadi izin langsung pemegangnya, jadi akses orangnya tidak berubah;
 *  - karyawan Talenta (employee_id terisi) berjabatan Security, Office Boy, atau
 *    General Affair yang belum memegang role jabatannya diberi role itu. Hanya
 *    sekali di sini; setelahnya role tetap diatur lewat menu User.
 *
 * Akun yang username-nya memuat "nonaktif" dilewati.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const RETIRED_ROLE = 'ga_cirebon_task';

    /**
     * Jabatan Talenta (huruf besar, persis) => role.
     */
    private const JOB_TITLE_ROLES = [
        'SECURITY' => 'security',
        'OFFICE BOY' => 'office_boy',
        'GENERAL AFFAIR OFFICER' => 'general_affair',
        'GENERAL AFFAIR COORDINATOR' => 'general_affair',
        'GENERAL AFFAIR ENGINEER' => 'general_affair',
        'GENERAL AFFAIR MKLI' => 'general_affair',
        'LEADER GENERAL AFFAIR' => 'general_affair',
        'FREELANCE ADMIN GA' => 'general_affair',
    ];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        DB::transaction(function (): void {
            $this->normalizeSecurityRole();
            $this->retireRole(self::RETIRED_ROLE);
            $this->assignRolesByJobTitle();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Tidak dibalik: role yang diberikan di sini tidak bisa dibedakan dari
        // yang diberikan lewat menu User setelahnya.
    }

    /**
     * Kolasi MySQL tidak membedakan huruf besar/kecil, jadi "Security" dan
     * "security" adalah baris yang sama: cukup diganti namanya.
     */
    private function normalizeSecurityRole(): void
    {
        $roles = DB::table($this->table('roles'))
            ->where('guard_name', self::GUARD)
            ->whereRaw('LOWER(name) = ?', ['security'])
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($roles->isEmpty()) {
            return;
        }

        $keep = $roles->firstWhere('name', 'security') ?? $roles->first();

        foreach ($roles as $role) {
            if ($role->id !== $keep->id) {
                $this->moveRole((int) $role->id, (int) $keep->id);
            }
        }

        DB::table($this->table('roles'))->where('id', $keep->id)->update(['name' => 'security']);
    }

    /**
     * Baris duplikat (hanya di database yang membedakan huruf besar/kecil):
     * izin dan pemegangnya digabung ke role yang dipertahankan.
     */
    private function moveRole(int $fromId, int $toId): void
    {
        $roleKey = $this->roleKey();

        foreach ([$this->table('role_has_permissions'), $this->table('model_has_roles')] as $pivot) {
            foreach (DB::table($pivot)->where($roleKey, $fromId)->get() as $row) {
                DB::table($pivot)->insertOrIgnore([...(array) $row, $roleKey => $toId]);
            }

            DB::table($pivot)->where($roleKey, $fromId)->delete();
        }

        DB::table($this->table('roles'))->where('id', $fromId)->delete();
    }

    private function retireRole(string $name): void
    {
        $role = DB::table($this->table('roles'))->where('name', $name)->where('guard_name', self::GUARD)->first();

        if ($role === null) {
            return;
        }

        $roleKey = $this->roleKey();
        $permissionKey = config('permission.column_names.permission_pivot_key') ?: 'permission_id';
        $modelKey = config('permission.column_names.model_morph_key') ?: 'model_id';

        $permissionIds = DB::table($this->table('role_has_permissions'))->where($roleKey, $role->id)->pluck($permissionKey);

        foreach (DB::table($this->table('model_has_roles'))->where($roleKey, $role->id)->get() as $holder) {
            foreach ($permissionIds as $permissionId) {
                DB::table($this->table('model_has_permissions'))->insertOrIgnore([
                    $permissionKey => $permissionId,
                    'model_type' => $holder->model_type,
                    $modelKey => $holder->{$modelKey},
                ]);
            }
        }

        DB::table($this->table('role_has_permissions'))->where($roleKey, $role->id)->delete();
        DB::table($this->table('model_has_roles'))->where($roleKey, $role->id)->delete();
        DB::table($this->table('roles'))->where('id', $role->id)->delete();
    }

    private function assignRolesByJobTitle(): void
    {
        $roleKey = $this->roleKey();
        $modelKey = config('permission.column_names.model_morph_key') ?: 'model_id';
        $modelType = (new User)->getMorphClass();

        $employees = DB::table('users')
            ->join('job_titles', 'job_titles.id', '=', 'users.job_title_id')
            ->whereNotNull('users.employee_id')
            ->where('users.employee_id', '!=', '')
            ->where(fn ($query) => $query->whereNull('users.username')->orWhere('users.username', 'not like', '%nonaktif%'))
            ->get(['users.id', 'job_titles.title']);

        $roleIds = [];

        foreach ($employees as $employee) {
            $roleName = self::JOB_TITLE_ROLES[strtoupper(trim((string) $employee->title))] ?? null;

            if ($roleName === null) {
                continue;
            }

            $roleIds[$roleName] ??= Role::findOrCreate($roleName, self::GUARD)->getKey();

            DB::table($this->table('model_has_roles'))->insertOrIgnore([
                $roleKey => $roleIds[$roleName],
                'model_type' => $modelType,
                $modelKey => $employee->id,
            ]);
        }
    }

    private function table(string $name): string
    {
        return config("permission.table_names.{$name}") ?: $name;
    }

    private function roleKey(): string
    {
        return config('permission.column_names.role_pivot_key') ?: 'role_id';
    }
};
