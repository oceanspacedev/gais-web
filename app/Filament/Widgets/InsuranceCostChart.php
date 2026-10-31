<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor > Asuransi & Sewa (sumo: "Jumlah Biaya Asuransi per bulan") = premi
 * stok + premi bangunan dari polis dan pembaharuan, dikelompokkan per bulan
 * tanggal berakhir. Baris TUTUP, REFUND, dan PEMBAHARUAN (sudah digantikan)
 * tidak dihitung, jadi tiap periode polis dihitung sekali.
 */
class InsuranceCostChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Premi Asuransi per Bulan Jatuh Tempo';

    protected ?string $description = 'Premi stok + bangunan dari periode polis yang berakhir di bulan itu.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 6;

    public ?string $filter = 'upcoming';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'upcoming' => '12 bulan ke depan',
            'past' => '12 bulan terakhir',
            'all' => 'Semua periode',
        ];
    }

    protected function getData(): array
    {
        $excluded = [...ContractStatus::endedValues(), ContractStatus::Renewed->value];
        $insuranceIds = InsuranceResource::getEloquentQuery()->select('insurances.id');
        [$from, $until] = $this->range();

        $policies = DB::table('insurances')
            ->whereIn('id', $insuranceIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $excluded)
            ->selectRaw(MonthSeries::sqlKey('expired_date').' AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total')
            ->groupBy('period');

        $renewals = DB::table('insurance_updates')
            ->whereIn('insurance_id', $insuranceIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $excluded)
            ->selectRaw(MonthSeries::sqlKey('expired_date').' AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total')
            ->groupBy('period');

        foreach ([$policies, $renewals] as $query) {
            if ($from !== null) {
                $query->where('expired_date', '>=', $from->toDateString());
            }

            if ($until !== null) {
                $query->where('expired_date', '<=', $until->toDateString());
            }
        }

        $totals = collect($policies->get())->concat($renewals->get())
            ->filter(fn (object $row): bool => filled($row->period))
            ->groupBy('period')
            ->map(fn (Collection $rows): float => (float) $rows->sum('total'))
            ->sortKeys();

        if ($from === null || $until === null) {
            // Semua periode (sumo): dari bulan terawal sampai terakhir yang punya data.
            $from = $totals->isEmpty() ? now()->startOfMonth() : Carbon::createFromFormat('Y-m', (string) $totals->keys()->first())->startOfMonth();
            $until = $totals->isEmpty() ? now()->startOfMonth() : Carbon::createFromFormat('Y-m', (string) $totals->keys()->last())->startOfMonth();
        }

        $months = MonthSeries::between($from, $until);

        return [
            'datasets' => [[
                'label' => 'Total premi',
                'data' => MonthSeries::fill($months, $totals, integer: false),
                'borderColor' => ChartPalette::SUCCESS,
                'backgroundColor' => ChartPalette::alpha(ChartPalette::SUCCESS, 0.2),
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('rupiah');
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function range(): array
    {
        return match ($this->filter) {
            'past' => [now()->startOfMonth()->subMonths(11), now()->endOfMonth()],
            'upcoming' => [now()->startOfMonth(), now()->startOfMonth()->addMonths(11)->endOfMonth()],
            default => [null, null],
        };
    }
}
