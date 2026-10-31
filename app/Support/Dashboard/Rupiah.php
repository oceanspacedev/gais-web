<?php

namespace App\Support\Dashboard;

/**
 * Format rupiah untuk dasbor: angka penuh "Rp 115.971.801.653" untuk keterangan,
 * dan ringkas "Rp 115,97 M" untuk nilai utama kartu yang ruangnya sempit.
 */
final class Rupiah
{
    public static function full(int|float|string|null $amount): string
    {
        return 'Rp '.number_format((float) ($amount ?? 0), 0, ',', '.');
    }

    public static function compact(int|float|string|null $amount): string
    {
        $value = (float) ($amount ?? 0);
        $abs = abs($value);

        [$divisor, $suffix] = match (true) {
            $abs >= 1_000_000_000_000 => [1_000_000_000_000, ' T'],
            $abs >= 1_000_000_000 => [1_000_000_000, ' M'],
            $abs >= 1_000_000 => [1_000_000, ' jt'],
            $abs >= 1_000 => [1_000, ' rb'],
            default => [1, ''],
        };

        $scaled = $value / $divisor;
        $decimals = $divisor === 1 ? 0 : ($abs / $divisor >= 100 ? 0 : ($abs / $divisor >= 10 ? 1 : 2));

        return 'Rp '.number_format($scaled, $decimals, ',', '.').$suffix;
    }

    public static function number(int|float|string|null $value, int $decimals = 0): string
    {
        return number_format((float) ($value ?? 0), $decimals, ',', '.');
    }

    /**
     * Persentase bagian dari total, "0%" bila totalnya nol.
     */
    public static function percent(int|float $part, int|float $total, int $decimals = 0): string
    {
        if ($total <= 0) {
            return '0%';
        }

        return number_format($part / $total * 100, $decimals, ',', '.').'%';
    }
}
