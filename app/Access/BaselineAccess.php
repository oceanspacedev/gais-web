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
 * Laporan orang lain tetap butuh ViewAll:ProblemReport (ProblemReport::visibility()).
 *
 * Karyawan yang sudah ditempatkan di divisi/toko (users.division_id) juga
 * mendapat izin pemohon (role requester): membuat pengajuan barang dan melihat
 * pengajuan divisinya, sebagai pengganti akun bersama toko. Tanpa divisi,
 * pengajuan barang tetap butuh role atau izin Shield.
 */
final class BaselineAccess
{
    /**
     * @var list<string>
     */
    public const PERMISSIONS = [
        'ViewAny:ProblemReport',
        'View:ProblemReport',
        'Create:ProblemReport',
    ];

    /**
     * Sama dengan izin role requester (migrasi 2026_10_13_000007).
     *
     * @var list<string>
     */
    public const DIVISION_MEMBER_PERMISSIONS = [
        'ViewAny:ItemRequest',
        'View:ItemRequest',
        'Create:ItemRequest',
        'ViewAny:Product',
        'View:Product',
        'View:SumoOverviewStats',
    ];

    public static function register(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (in_array($ability, self::PERMISSIONS, true)) {
                return true;
            }

            if ($user->division_id !== null && in_array($ability, self::DIVISION_MEMBER_PERMISSIONS, true)) {
                return true;
            }

            return null;
        });
    }
}
