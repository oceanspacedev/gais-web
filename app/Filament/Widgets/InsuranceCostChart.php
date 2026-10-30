<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Beranda sumo: "Jumlah Biaya Asuransi per bulan" = premi stok + premi bangunan
 * dari polis dan pembaharuan, dikelompokkan per bulan tanggal berakhir. Baris
 * TUTUP, REFUND, dan PEMBAHARUAN (sudah digantikan) tidak dihitung, jadi tiap
 * periode polis dihitung sekali. Sumo menampilkan semua bulan; di sini bisa
 * dibatasi 12 bulan terakhir atau 12 bulan ke depan.
 */
class InsuranceCostChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 15;

    protected ?string $heading = 'Biaya Premi Asuransi per Bulan Jatuh Tempo';

    // Tanpa polling 5 detik bawaan Filament: angkanya tidak perlu real-time dan querynya berat.
    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    public ?string $filter = 'all';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Semua periode',
            'past' => '12 bulan terakhir',
            'upcoming' => '12 bulan ke depan',
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
            ->selectRaw("DATE_FORMAT(expired_date, '%Y-%m') AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total")
            ->groupBy('period');

        $renewals = DB::table('insurance_updates')
            ->whereIn('insurance_id', $insuranceIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', $excluded)
            ->selectRaw("DATE_FORMAT(expired_date, '%Y-%m') AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total")
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

        $labels = [];
        $data = [];

        for ($month = $from->copy()->startOfMonth(); $month->lte($until); $month->addMonth()) {
            $key = $month->format('Y-m');
            $labels[] = Carbon::instance($month)->locale('id')->isoFormat('MMM YYYY');
            $data[] = $totals[$key] ?? 0;
        }

        return [
            'datasets' => [[
                'label' => 'Total premi (Rp)',
                'data' => $data,
                'borderColor' => '#10b981',
                'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                'fill' => true,
            ]],
            'labels' => $labels,
        ];
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
