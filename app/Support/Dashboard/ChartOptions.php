<?php

namespace App\Support\Dashboard;

use Filament\Support\RawJs;

/**
 * Opsi Chart.js untuk grafik dasbor. Sumbu nilai diformat di browser (angka
 * Indonesia, rupiah ringkas "Rp 1,2 jt") dan tooltip menampilkan nilai penuh,
 * jadi widget cukup mengirim angka mentah.
 *
 * Format sumbu: `int` (angka bulat), `rupiah` (ringkas di sumbu, penuh di tooltip),
 * `km`, `jam`, `menit`.
 */
final class ChartOptions
{
    /**
     * Grafik batang / garis dengan satu sumbu nilai.
     */
    public static function value(string $format = 'int', bool $stacked = false, bool $horizontal = false, bool $legend = false): RawJs
    {
        $valueAxis = $horizontal ? 'x' : 'y';
        $labelAxis = $horizontal ? 'y' : 'x';

        return self::build([
            'indexAxis' => $horizontal ? 'y' : 'x',
            'plugins' => ['legend' => ['display' => $legend, 'position' => 'bottom']],
            'scales' => [
                $valueAxis => ['stacked' => $stacked, 'beginAtZero' => true],
                $labelAxis => ['stacked' => $stacked, 'grid' => ['display' => false]],
            ],
        ], [$valueAxis => $format]);
    }

    /**
     * Batang (sumbu kiri) + garis (sumbu kanan `y1`) dengan satuan berbeda,
     * mis. jumlah pembelian dan nilainya per bulan.
     */
    public static function combo(string $leftFormat = 'int', string $rightFormat = 'rupiah'): RawJs
    {
        return self::build([
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'y' => ['beginAtZero' => true, 'position' => 'left'],
                'y1' => ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]],
                'x' => ['grid' => ['display' => false]],
            ],
        ], ['y' => $leftFormat, 'y1' => $rightFormat]);
    }

    /**
     * Batang horizontal dengan dua satuan (mis. jumlah aset dan nilainya per kategori).
     */
    public static function horizontalCombo(string $topFormat = 'int', string $bottomFormat = 'rupiah'): RawJs
    {
        return self::build([
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => [
                'x' => ['beginAtZero' => true, 'position' => 'top'],
                'x1' => ['beginAtZero' => true, 'position' => 'bottom', 'grid' => ['drawOnChartArea' => false]],
                'y' => ['grid' => ['display' => false]],
            ],
        ], ['x' => $topFormat, 'x1' => $bottomFormat]);
    }

    /**
     * Doughnut / pie: legenda di kanan, tooltip "label: nilai (persen)".
     */
    public static function doughnut(string $format = 'int'): RawJs
    {
        $json = self::json([
            'plugins' => ['legend' => ['display' => true, 'position' => 'right']],
            'cutout' => '62%',
        ]);
        $formatters = self::formatters();

        return RawJs::make(<<<JS
            (() => {
                {$formatters}
                const options = {$json};
                options.plugins.tooltip = {
                    callbacks: {
                        label: (ctx) => {
                            const total = ctx.dataset.data.reduce((sum, value) => sum + (Number(value) || 0), 0);
                            const value = Number(ctx.parsed) || 0;
                            const percent = total > 0 ? Math.round(value / total * 1000) / 10 : 0;
                            return ' ' + ctx.label + ': ' + fmt['{$format}'](value) + ' (' + percent.toLocaleString('id-ID') + '%)';
                        },
                    },
                };
                return options;
            })()
            JS);
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $axisFormats  id sumbu => format
     */
    private static function build(array $options, array $axisFormats): RawJs
    {
        $json = self::json($options);
        $axes = self::json($axisFormats);
        $formatters = self::formatters();

        return RawJs::make(<<<JS
            (() => {
                {$formatters}
                const axes = {$axes};
                const options = {$json};
                const horizontal = options.indexAxis === 'y';
                options.scales = options.scales ?? {};
                for (const [axis, format] of Object.entries(axes)) {
                    options.scales[axis] = options.scales[axis] ?? {};
                    options.scales[axis].ticks = {
                        ...(options.scales[axis].ticks ?? {}),
                        precision: 0,
                        callback: (value) => (format === 'rupiah' ? fmt.rupiahCompact : fmt[format] ?? fmt.int)(value),
                    };
                }
                options.plugins = options.plugins ?? {};
                options.plugins.tooltip = {
                    ...(options.plugins.tooltip ?? {}),
                    callbacks: {
                        label: (ctx) => {
                            const axis = ctx.dataset.yAxisID ?? ctx.dataset.xAxisID ?? (horizontal ? 'x' : 'y');
                            const format = fmt[axes[axis] ?? 'int'] ?? fmt.int;
                            const value = horizontal ? ctx.parsed.x : ctx.parsed.y;
                            return ' ' + (ctx.dataset.label ? ctx.dataset.label + ': ' : '') + format(value);
                        },
                    },
                };
                return options;
            })()
            JS);
    }

    private static function formatters(): string
    {
        return <<<'JS'
            const number = (digits) => new Intl.NumberFormat('id-ID', { maximumFractionDigits: digits });
            const fmt = {
                int: (v) => number(0).format(Number(v) || 0),
                rupiah: (v) => 'Rp ' + number(0).format(Number(v) || 0),
                rupiahCompact: (v) => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(Number(v) || 0),
                km: (v) => number(0).format(Number(v) || 0) + ' km',
                jam: (v) => number(1).format(Number(v) || 0) + ' jam',
                menit: (v) => number(0).format(Number(v) || 0) + ' mnt',
            };
            JS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function json(array $data): string
    {
        // Opsi dirender ke atribut HTML x-data="chart({ options: ... })" (dibatasi tanda
        // kutip ganda), jadi literal JS-nya harus memakai kutip tunggal.
        return str_replace('"', "'", (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
