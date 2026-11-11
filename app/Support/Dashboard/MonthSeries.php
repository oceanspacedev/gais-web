<?php

namespace App\Support\Dashboard;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deret bulan untuk grafik tren dasbor: kunci "Y-m" berurutan beserta label
 * Indonesia ("Okt 2026"), dan pengisi nol untuk bulan yang tidak punya data.
 */
final class MonthSeries
{
    /**
     * Sekian bulan terakhir, berakhir di bulan ini.
     *
     * @return array<string, string> kunci "Y-m" => label
     */
    public static function past(int $months, ?CarbonInterface $until = null): array
    {
        $end = Carbon::instance($until ?? now())->startOfMonth();

        return self::between($end->copy()->subMonths($months - 1), $end);
    }

    /**
     * Sekian bulan ke depan, dimulai dari bulan ini.
     *
     * @return array<string, string> kunci "Y-m" => label
     */
    public static function upcoming(int $months, ?CarbonInterface $from = null): array
    {
        $start = Carbon::instance($from ?? now())->startOfMonth();

        return self::between($start, $start->copy()->addMonths($months - 1));
    }

    /**
     * @return array<string, string>
     */
    public static function between(CarbonInterface $from, CarbonInterface $until): array
    {
        $series = [];

        for ($month = Carbon::instance($from)->startOfMonth(); $month->lte($until); $month->addMonth()) {
            $series[$month->format('Y-m')] = self::label($month->format('Y-m'));
        }

        return $series;
    }

    public static function label(string $key): string
    {
        return Carbon::createFromFormat('Y-m', $key)->locale('id')->isoFormat('MMM YYYY');
    }

    /**
     * Tanggal awal rentang `past()` untuk klausa WHERE.
     */
    public static function startOfPast(int $months, ?CarbonInterface $until = null): Carbon
    {
        return Carbon::instance($until ?? now())->startOfMonth()->subMonths($months - 1);
    }

    /**
     * Nilai per bulan sesuai urutan $series; bulan tanpa data diisi 0.
     *
     * @param  array<string, string>  $series
     * @param  Collection<int|string, mixed>|array<string, int|float>  $totals  kunci "Y-m" => nilai
     * @return list<int|float>
     */
    public static function fill(array $series, Collection|array $totals, bool $integer = true): array
    {
        $totals = $totals instanceof Collection ? $totals->all() : $totals;

        return array_map(
            fn (string $key): int|float => $integer ? (int) ($totals[$key] ?? 0) : round((float) ($totals[$key] ?? 0)),
            array_keys($series),
        );
    }

    /**
     * Ekspresi SQL kunci bulan (MySQL: DATE_FORMAT, SQLite: strftime) untuk GROUP BY.
     */
    public static function sqlKey(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
