<?php

namespace App\Access;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Akses dasar semua karyawan, apa pun role-nya (OB, satpam, IT, atau belum
 * diberi role): izin di PERMISSIONS selalu dianggap dimiliki. Policy tetap
 * memeriksa izin Shield seperti biasa; yang berubah hanya jawabannya.
 *
 * Saat ini: membuat laporan gangguan ke GA dan melihat laporannya sendiri,
 * serta membuka daftar Pengajuan Barang. Laporan/pengajuan orang lain tetap
 * dibatasi visibility() masing-masing model. Karyawan tanpa divisi memilih
 * divisinya dulu sebelum melapor (ListProblemReports), supaya laporannya
 * terlihat oleh rekan sedivisi.
 *
 * Karyawan yang sudah ditempatkan di divisi/toko (users.division_id) juga
 * mendapat izin pemohon (role requester): membuat pengajuan barang dan melihat
 * pengajuan divisinya, sebagai pengganti akun bersama toko. Karyawan yang belum
 * punya divisi memilihnya sekali di halaman Pengajuan Barang atau Laporan
 * Gangguan (ChoosesOwnDivision); setelah itu hanya admin yang bisa mengubahnya.
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
        'ViewAny:ItemRequest',
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
