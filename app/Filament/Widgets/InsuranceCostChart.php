<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Beranda sumo: "Jumlah Biaya Asuransi per bulan" = premi stok + premi bangunan
 * dari polis dan pembaharuan yang mulai di bulan itu, 12 bulan terakhir.
 */
class InsuranceCostChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 15;

    protected ?string $heading = 'Biaya Premi Asuransi per Bulan';

    // Tanpa polling 5 detik bawaan Filament: angkanya tidak perlu real-time dan querynya berat.
    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

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

    protected function getData(): array
    {
        $from = now()->startOfMonth()->subMonths(11);
        $insuranceIds = InsuranceResource::getEloquentQuery()->select('insurances.id');

        $policies = DB::table('insurances')
            ->whereIn('id', $insuranceIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ContractStatus::endedValues())
            ->where('join_date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(join_date, '%Y-%m') AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total")
            ->groupBy('period');

        $renewals = DB::table('insurance_updates')
            ->whereIn('insurance_id', $insuranceIds)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ContractStatus::endedValues())
            ->where('join_date', '>=', $from->toDateString())
            ->selectRaw("DATE_FORMAT(join_date, '%Y-%m') AS period, SUM(COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)) AS total")
            ->groupBy('period');

        $totals = collect($policies->get())->concat($renewals->get())
            ->groupBy('period')
            ->map(fn ($rows): float => (float) collect($rows)->sum('total'));

        $labels = [];
        $data = [];

        for ($month = $from->copy(); $month->lte(now()->startOfMonth()); $month->addMonth()) {
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
}
