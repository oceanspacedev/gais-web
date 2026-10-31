<?php

namespace App\Support\Dashboard;

/**
 * Warna grafik dasbor, satu set untuk semua widget supaya terbaca sebagai satu
 * sistem: biru = utama/aktif, hijau = selesai/sehat, kuning = menunggu,
 * merah = masalah/insiden, abu-abu = selesai pasif (dijual, tutup).
 */
final class ChartPalette
{
    public const PRIMARY = '#3b82f6';

    public const SUCCESS = '#10b981';

    public const WARNING = '#f59e0b';

    public const DANGER = '#ef4444';

    public const INFO = '#0ea5e9';

    public const VIOLET = '#8b5cf6';

    public const GRAY = '#9ca3af';

    public const TEAL = '#14b8a6';

    public const PINK = '#ec4899';

    public const ORANGE = '#f97316';

    /**
     * Urutan warna untuk deret kategori (doughnut, batang per kategori).
     *
     * @return list<string>
     */
    public static function categorical(): array
    {
        return [self::PRIMARY, self::SUCCESS, self::WARNING, self::DANGER, self::VIOLET, self::TEAL, self::PINK, self::ORANGE, self::INFO, self::GRAY];
    }

    /**
     * @return list<string>
     */
    public static function categoricalFor(int $count): array
    {
        $colors = self::categorical();

        return array_map(fn (int $i): string => $colors[$i % count($colors)], range(0, max(0, $count - 1)));
    }

    public static function alpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha);
    }
}
