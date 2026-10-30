<?php

namespace App\Support;

use App\Enums\ContractStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Warna jatuh tempo kontrak (sumo: warna baris daftar asuransi dan sewa).
 *
 * Asuransi: kuning 15-30 hari sebelum berakhir, merah 14 hari atau kurang
 * (termasuk lewat), abu-abu bila kontraknya sudah tutup/refund.
 * Sewa memakai tanggal reminder = tanggal berakhir dikurangi "bulan sebelum":
 * kuning 30 hari sebelum tanggal reminder, merah sejak tanggal reminder,
 * abu-abu bila tutup/refund.
 */
final class ContractDue
{
    public static function insuranceColor(?CarbonInterface $expiry, ?ContractStatus $status = null, ?CarbonInterface $today = null): ?string
    {
        if ($expiry === null) {
            return null;
        }

        $days = self::daysUntil($expiry, $today);

        if ($days > 30) {
            return null;
        }

        if ($days > 14) {
            return 'warning';
        }

        return self::isActive($status) ? 'danger' : 'gray';
    }

    public static function rentColor(?CarbonInterface $expiry, mixed $monthsBefore, ?ContractStatus $status = null, ?CarbonInterface $today = null): ?string
    {
        $reminder = self::reminderDate($expiry, $monthsBefore);

        if ($reminder === null) {
            return null;
        }

        $days = self::daysUntil($reminder, $today);

        if ($days > 30) {
            return null;
        }

        if ($days > 0) {
            return 'warning';
        }

        return self::isActive($status) ? 'danger' : 'gray';
    }

    /**
     * Tanggal mulai mengingatkan sewa: berakhir dikurangi "bulan sebelum" (kosong = 0).
     */
    public static function reminderDate(?CarbonInterface $expiry, mixed $monthsBefore): ?CarbonInterface
    {
        if ($expiry === null) {
            return null;
        }

        return Carbon::instance($expiry)->startOfDay()->subMonths(max(0, (int) $monthsBefore));
    }

    /**
     * Hari sampai tanggal itu dari hari ini; negatif bila sudah lewat.
     */
    public static function daysUntil(CarbonInterface $date, ?CarbonInterface $today = null): int
    {
        $today = ($today ?? today())->copy()->startOfDay();

        return (int) $today->diffInDays(Carbon::instance($date)->startOfDay(), false);
    }

    private static function isActive(?ContractStatus $status): bool
    {
        return $status === null || $status->isActive();
    }
}
