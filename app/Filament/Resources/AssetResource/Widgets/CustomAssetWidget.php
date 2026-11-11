<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Enums\AssetCondition;
use App\Enums\NbhStatus;
use App\Filament\Resources\AssetReconciliationResource;
use App\Filament\Resources\AssetResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\AssetReconciliation;
use App\Support\Dashboard\MonthSeries;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasbor > Aset: jumlah, nilai perolehan, kondisi, pembelian setahun terakhir,
 * dan audit terakhir. Angka mengikuti akses badan usaha pengguna, sama seperti
 * daftar aset. Nama kelas dipertahankan karena izin `widget_CustomAssetWidget`
 * sudah dipegang role di produksi.
 */
class CustomAssetWidget extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Ringkasan Aset';

    protected ?string $description = 'Jumlah, nilai perolehan, dan kondisi aset yang bisa Anda lihat.';

    // Tanpa polling bawaan Filament: angkanya tidak perlu real-time dan querynya cukup berat.
    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $byCondition = $this->assets()
            ->toBase()
            ->selectRaw('condition_status, COUNT(*) AS total, COALESCE(SUM(qty), 0) AS units, COALESCE(SUM(item_price * qty), 0) AS value, COALESCE(SUM(sold_price), 0) AS sold_value')
            ->groupBy('condition_status')
            ->get()
            ->keyBy('condition_status');

        $count = fn (AssetCondition ...$conditions): int => (int) collect($conditions)->sum(fn (AssetCondition $condition): int => (int) ($byCondition[$condition->value]->total ?? 0));
        $value = fn (AssetCondition ...$conditions): float => (float) collect($conditions)->sum(fn (AssetCondition $condition): float => (float) ($byCondition[$condition->value]->value ?? 0));

        $total = $count(...AssetCondition::cases());
        $units = (int) $byCondition->sum('units');
        $available = $count(AssetCondition::Available);
        $inUse = $count(AssetCondition::Transferred);
        $incidents = $count(AssetCondition::Damaged, AssetCondition::Lost);
        $sold = $count(AssetCondition::Sold);
        $activeValue = $value(AssetCondition::Available, AssetCondition::Transferred);

        $holders = (int) $this->assets()->toBase()->where('condition_status', AssetCondition::Transferred->value)->distinct()->count('recipient_id');
        $nbh = $this->assets()->toBase()->whereIn('condition_status', AssetCondition::incidentValues())->selectRaw('nbh_status, COUNT(*) AS total')->groupBy('nbh_status')->pluck('total', 'nbh_status');

        $months = MonthSeries::past(12);
        $purchases = $this->assets()->toBase()
            ->where('purchase_date', '>=', MonthSeries::startOfPast(12)->toDateString())
            ->selectRaw(MonthSeries::sqlKey('purchase_date').' AS period, COUNT(*) AS total, COALESCE(SUM(item_price * qty), 0) AS value')
            ->groupBy('period')
            ->get()
            ->keyBy('period');

        $stats = [
            Stat::make('Jumlah Aset', Rupiah::number($total))
                ->description(Rupiah::number($units).' unit · dipegang '.Rupiah::number($holders).' orang')
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary')
                ->url(AssetResource::getUrl()),
            Stat::make('Nilai Aset Aktif', Rupiah::compact($activeValue))
                ->description('Tersedia '.Rupiah::compact($value(AssetCondition::Available)).' · Digunakan '.Rupiah::compact($value(AssetCondition::Transferred)))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Tersedia (Stok)', Rupiah::number($available))
                ->description(Rupiah::percent($available, $total).' dari aset · siap diserahterimakan')
                ->descriptionIcon(Heroicon::OutlinedArchiveBox)
                ->color('success')
                ->url(self::conditionUrl(AssetCondition::Available)),
            Stat::make('Digunakan', Rupiah::number($inUse))
                ->description(Rupiah::percent($inUse, $total).' dari aset · '.Rupiah::number($holders).' pemegang')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('warning')
                ->url(self::conditionUrl(AssetCondition::Transferred)),
            Stat::make('Rusak / Hilang', Rupiah::number($incidents))
                ->description(Rupiah::number((int) ($nbh[NbhStatus::Pending->value] ?? 0)).' menunggu NBH · '.Rupiah::number((int) ($nbh[NbhStatus::Resolved->value] ?? 0)).' NBH selesai')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($incidents > 0 ? 'danger' : 'gray')
                ->url(self::conditionUrl(AssetCondition::Damaged, AssetCondition::Lost)),
            Stat::make('Dijual', Rupiah::number($sold))
                ->description('Terjual '.Rupiah::compact((float) ($byCondition[AssetCondition::Sold->value]->sold_value ?? 0)).' dari perolehan '.Rupiah::compact($value(AssetCondition::Sold)))
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->color('gray')
                ->url(self::conditionUrl(AssetCondition::Sold)),
            Stat::make('Pembelian 12 Bulan', Rupiah::number((int) $purchases->sum('total')))
                ->description(Rupiah::compact((float) $purchases->sum('value')).' nilai perolehan')
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->chart(MonthSeries::fill($months, $purchases->map(fn (object $row): int => (int) $row->total)))
                ->chartColor('info')
                ->color('info'),
        ];

        $stats[] = AssetReconciliationResource::canViewAny()
            ? $this->auditStat()
            : Stat::make('Lokasi Terpakai', Rupiah::number((int) $this->assets()->toBase()->distinct()->count('asset_location_id')))
                ->description('lokasi yang punya aset')
                ->descriptionIcon(Heroicon::OutlinedMapPin)
                ->color('gray');

        return $stats;
    }

    protected function auditStat(): Stat
    {
        /** @var AssetReconciliation|null $audit */
        $audit = AssetReconciliationResource::getEloquentQuery()
            ->whereIn('status', [AssetReconciliation::STATUS_COMPARED, AssetReconciliation::STATUS_ALIGNED, AssetReconciliation::STATUS_APPLIED])
            ->latest('created_at')
            ->first();

        if ($audit === null) {
            return Stat::make('Audit Aset Terakhir', '-')
                ->description('Belum ada impor audit aset')
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
                ->color('gray')
                ->url(AssetReconciliationResource::getUrl());
        }

        $applied = $audit->status === AssetReconciliation::STATUS_APPLIED;
        $when = ($applied ? $audit->applied_at : $audit->compared_at) ?? $audit->created_at;

        return Stat::make('Audit Aset Terakhir', $when?->format('d/m/Y') ?? '-')
            ->description(sprintf(
                '%s baris · %s selisih · %s terblokir · %s',
                Rupiah::number((int) $audit->total_rows),
                Rupiah::number((int) $audit->gap_rows),
                Rupiah::number((int) $audit->blocked_rows),
                $applied ? 'sudah diterapkan' : 'belum diterapkan',
            ))
            ->descriptionIcon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color($applied ? 'success' : 'warning')
            ->url(AssetReconciliationResource::getUrl('view', ['record' => $audit]));
    }

    protected function assets(): Builder
    {
        return AssetResource::getEloquentQuery();
    }

    protected static function conditionUrl(AssetCondition ...$conditions): string
    {
        return AssetResource::getUrl(parameters: [
            'tableFilters' => ['condition_status' => ['values' => array_map(fn (AssetCondition $condition): string => $condition->value, $conditions)]],
        ]);
    }
}
