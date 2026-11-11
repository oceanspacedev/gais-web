<?php

namespace App\Exports;

use App\Exports\Concerns\ExportsTemplateRows;
use App\Filament\Resources\ProblemReportResource;
use App\Models\ProblemReport;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor laporan gangguan dalam rentang tanggal pelaporan dengan kolom ekspor
 * sumo (/problemReport/export) ditambah kode laporan di depan. Hanya laporan
 * yang boleh dilihat pembaca yang ikut.
 */
class ProblemReportExport implements FromCollection, WithHeadings, WithMapping
{
    use ExportsTemplateRows;

    public function __construct(private readonly CarbonInterface $from, private readonly CarbonInterface $until) {}

    public function fileName(): string
    {
        return sprintf('laporan-gangguan-%s-%s.xlsx', $this->from->format('Ymd'), $this->until->format('Ymd'));
    }

    /**
     * @return Collection<int, ProblemReport>
     */
    public function collection(): Collection
    {
        return ProblemReportResource::getEloquentQuery()
            ->with(['user', 'category', 'closedBy'])
            ->whereBetween('problem_reports.reported_at', [$this->from->copy()->startOfDay(), $this->until->copy()->endOfDay()])
            ->orderBy('problem_reports.reported_at')
            ->orderBy('problem_reports.id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['kode', 'pelapor', 'tgl pelaporan', 'kategori', 'detail_pelaporan', 'status', 'penjadwalan', 'diproses oleh', 'diproses pada', 'hasil pengerjaan', 'status akhir'];
    }

    /**
     * @param  ProblemReport  $report
     * @return list<mixed>
     */
    public function map($report): array
    {
        return $this->orderedByHeadings([
            'kode' => $report->code,
            'pelapor' => $report->user?->name,
            'tgl pelaporan' => $report->reported_at?->format('Y-m-d H:i:s'),
            'kategori' => $report->category?->name,
            'detail_pelaporan' => $report->description,
            'status' => $report->status?->value,
            'penjadwalan' => $report->scheduled_at?->format('Y-m-d'),
            'diproses oleh' => $report->closedBy?->name,
            'diproses pada' => $report->closed_at?->format('Y-m-d H:i:s'),
            'hasil pengerjaan' => $report->result_desc,
            'status akhir' => $report->isConfirmed() ? 'SELESAI' : 'MENUNGGU',
        ]);
    }
}
