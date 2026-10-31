<?php

namespace App\Filament\Widgets;

use App\Enums\AssetTransferDocumentType;
use App\Filament\Resources\AssetTransferResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Support\Dashboard\ChartOptions;
use App\Support\Dashboard\ChartPalette;
use App\Support\Dashboard\MonthSeries;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

/**
 * Dasbor > Aset: jumlah berita acara per bulan, ditumpuk per jenis dokumen
 * (serah terima dari stok, pengalihan antar pemegang, pengembalian ke stok).
 */
class AssetTransferTrendChart extends ChartWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Mutasi Aset (Berita Acara)';

    protected ?string $description = 'Berita acara per bulan menurut jenis dokumen, 12 bulan terakhir.';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $months = MonthSeries::past(12);

        $rows = AssetTransferResource::getEloquentQuery()->toBase()
            ->where('transfer_date', '>=', MonthSeries::startOfPast(12)->toDateTimeString())
            ->selectRaw(MonthSeries::sqlKey('transfer_date').' AS period, document_type, COUNT(*) AS total')
            ->groupBy('period', 'document_type')
            ->get()
            ->groupBy('document_type');

        $datasets = [];

        foreach (AssetTransferDocumentType::cases() as $type) {
            /** @var Collection<int, object> $perMonth */
            $perMonth = $rows->get($type->value, collect());

            $datasets[] = [
                'label' => $type->code().' · '.ucwords(strtolower(str_replace('BERITA ACARA ', '', $type->label()))),
                'data' => MonthSeries::fill($months, $perMonth->keyBy('period')->map(fn (object $row): int => (int) $row->total)),
                'backgroundColor' => self::color($type),
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_values($months),
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return ChartOptions::value('int', stacked: true, legend: true);
    }

    public static function color(AssetTransferDocumentType $type): string
    {
        return match ($type) {
            AssetTransferDocumentType::SerahTerima => ChartPalette::PRIMARY,
            AssetTransferDocumentType::PengalihanBarang => ChartPalette::SUCCESS,
            AssetTransferDocumentType::PengembalianBarang => ChartPalette::DANGER,
        };
    }
}
