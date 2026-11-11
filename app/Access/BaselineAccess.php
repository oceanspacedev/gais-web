<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Akses dasar semua karyawan, apa pun role-nya (OB, satpam, IT, atau belum
 * diberi role): izin di PERMISSIONS selalu dianggap dimiliki. Policy tetap
 * memeriksa izin Shield seperti biasa; yang berubah hanya jawabannya.
 *
 * Saat ini: membuat laporan gangguan ke GA dan melihat laporannya sendiri.
 * Laporan orang lain tetap butuh view_all_problem::report (ProblemReport::visibility()).
 */
final class BaselineAccess
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'view_any_problem::report',
        'view_problem::report',
        'create_problem::report',
    ];

    public static function register(): void
    {
        Gate::before(fn (User $user, string $ability): ?bool => in_array($ability, self::PERMISSIONS, true) ? true : null);
    }
}
