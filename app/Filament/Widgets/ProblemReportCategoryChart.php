<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ProblemReportResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * Beranda sumo: "Kategori Laporan Gangguan Tertinggi", setahun terakhir.
 */
class ProblemReportCategoryChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 14;

    protected ?string $heading = 'Jenis Gangguan Terbanyak';

    protected ?string $maxHeight = '320px';

    public static function canView(): bool
    {
        $permission = static::getWidgetPermission();
        $user = Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : parent::canView();
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = ProblemReportResource::getEloquentQuery()
            ->leftJoin('problem_report_categories', 'problem_report_categories.id', '=', 'problem_reports.problem_report_category_id')
            ->where('problem_reports.reported_at', '>=', now()->subYear())
            ->selectRaw('COALESCE(problem_report_categories.name, "-") AS category, COUNT(*) AS total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Jumlah laporan',
                'data' => $rows->pluck('total')->map(fn ($value): int => (int) $value)->all(),
                'backgroundColor' => ['#3b82f6', '#f59e0b', '#ef4444', '#10b981', '#8b5cf6', '#ec4899', '#6b7280', '#14b8a6'],
            ]],
            'labels' => $rows->pluck('category')->all(),
        ];
    }
}
