<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Memberi role default (requester) ke semua user yang belum punya role sama
 * sekali, supaya terlihat sebagai pemohon di menu Pengguna dan bisa
 * di-impersonate. Aman dijalankan ulang: user yang sudah punya role apa pun
 * dilewati. Pemohon tanpa divisi tetap diminta memilih divisinya sebelum
 * membuat pengajuan (User::needsToChooseDivision()).
 */
class AssignDefaultRoleCommand extends Command
{
    protected $signature = 'users:assign-default-role
        {--role=requester : Nama role yang diberikan}
        {--dry-run : Hanya hitung, tanpa menyimpan}';

    protected $description = 'Beri role default (requester) ke semua user yang belum punya role';

    public function handle(): int
    {
        $role = Role::query()->where('name', $this->option('role'))->where('guard_name', 'web')->first();

        if ($role === null) {
            $this->error("Role '{$this->option('role')}' tidak ditemukan.");

            return self::FAILURE;
        }

        $query = User::query()->whereDoesntHave('roles');
        $total = $query->count();

        if ($this->option('dry-run')) {
            $this->info("{$total} user tanpa role akan diberi role {$role->name} (dry run, tidak disimpan).");

            return self::SUCCESS;
        }

        $assigned = 0;

        $query->chunkById(200, function (Collection $users) use ($role, &$assigned): void {
            foreach ($users as $user) {
                /** @var User $user */
                $user->assignRole($role);
                $assigned++;
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("{$assigned} user diberi role {$role->name}.");

        return self::SUCCESS;
    }
}
