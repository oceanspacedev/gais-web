<?php

namespace App\Filament\Widgets;

use App\Enums\ItemRequestApprovalType;
use App\Enums\ItemRequestClientStatus;
use App\Filament\Resources\ItemRequestResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\ItemRequestSetting;
use App\Support\Dashboard\MonthSeries;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor > Pengajuan Barang: antrean per tahap, hasil tahun berjalan, biaya,
 * dan periode cutoff yang sedang berlaku. Angka mengikuti hak lihat pengguna
 * (pemohon melihat pengajuannya sendiri). Nama kelas dipertahankan karena izin
 * `widget_SumoOverviewStats` sudah dipegang role pemohon, accounting, dan executor.
 */
class SumoOverviewStats extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Ringkasan Pengajuan Barang';

    protected ?string $pollingInterval = null;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getDescription(): ?string
    {
        $period = ItemRequestSetting::current();

        if ($period?->closed_date === null) {
            return 'Periode pengajuan belum diatur.';
        }

        $state = today()->between($period->open_date ?? $period->closed_date, $period->closed_date) ? 'sedang dibuka' : 'di luar jadwal';

        return sprintf(
            'Periode %s: %s s.d. %s (%s).',
            $period->request_detail ?? 'pengajuan',
            $period->open_date?->format('d/m/Y') ?? '-',
            $period->closed_date->format('d/m/Y'),
            $state,
        );
    }

    protected function getStats(): array
    {
        $yearStart = now()->startOfYear();

        $thisYear = (int) $this->requests()->where('requested_at', '>=', $yearStart)->count();
        $accepted = (int) $this->requests()->where('requested_at', '>=', $yearStart)->where('client_status', ItemRequestClientStatus::Accepted->value)->count();
        $cancelled = (int) $this->requests()->where('requested_at', '>=', $yearStart)->where('client_status', ItemRequestClientStatus::Cancelled->value)->count();
        $open = (int) $this->requests()->whereIn('client_status', [ItemRequestClientStatus::Waiting->value, ItemRequestClientStatus::Processed->value, ItemRequestClientStatus::Closed->value])->count();

        $awaitingAccounting = (int) $this->waiting()
            ->whereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', true))
            ->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approval_type', ItemRequestApprovalType::Accounting->value)->whereNull('approved_by_user_id'))
            ->count();
        $awaitingManager = (int) $this->pastAccounting()
            ->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approval_type', ItemRequestApprovalType::Manager->value)->whereNull('approved_by_user_id'))
            ->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approval_type', ItemRequestApprovalType::Executor->value)->whereNull('approved_by_user_id'))
            ->count();
        $readyForExecutor = (int) $this->pastAccounting()
            ->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approval_type', ItemRequestApprovalType::Manager->value)->whereNotNull('approved_by_user_id'))
            ->whereHas('approvals', fn (Builder $approvals) => $approvals->where('approval_type', ItemRequestApprovalType::Executor->value)->whereNull('approved_by_user_id'))
            ->count();
        $processing = (int) $this->requests()->where('client_status', ItemRequestClientStatus::Processed->value)->count();
        $awaitingRequester = (int) $this->requests()->where('client_status', ItemRequestClientStatus::Closed->value)->count();

        $months = MonthSeries::past(12);
        $perMonth = $this->requests()->toBase()
            ->where('requested_at', '>=', MonthSeries::startOfPast(12))
            ->selectRaw(MonthSeries::sqlKey('requested_at').' AS period, COUNT(*) AS total')
            ->groupBy('period')
            ->pluck('total', 'period');

        $cost = (float) $this->requests()->toBase()
            ->join('item_request_details', 'item_request_details.item_request_id', '=', 'item_requests.id')
            ->leftJoin('products', 'products.id', '=', 'item_request_details.product_id')
            ->whereNull('item_request_details.deleted_at')
            ->where('item_requests.requested_at', '>=', $yearStart)
            ->where('item_requests.client_status', '<>', ItemRequestClientStatus::Cancelled->value)
            ->sum(DB::raw('COALESCE(item_request_details.qty_approved, item_request_details.qty_request) * COALESCE(products.price, 0)'));

        return [
            Stat::make('Pengajuan Tahun Ini', Rupiah::number($thisYear))
                ->description(Rupiah::number($open).' masih terbuka')
                ->descriptionIcon(Heroicon::OutlinedInboxStack)
                ->chart(MonthSeries::fill($months, $perMonth))
                ->chartColor('primary')
                ->color('primary')
                ->url(ItemRequestResource::getUrl()),
            Stat::make('Menunggu Persetujuan', Rupiah::number($awaitingAccounting + $awaitingManager))
                ->description(Rupiah::number($awaitingAccounting).' Accounting · '.Rupiah::number($awaitingManager).' Manager')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color($awaitingAccounting + $awaitingManager > 0 ? 'warning' : 'gray')
                ->url(self::statusUrl(ItemRequestClientStatus::Waiting)),
            Stat::make('Siap Diproses Executor', Rupiah::number($readyForExecutor))
                ->description('persetujuan lengkap, menunggu executor')
                ->descriptionIcon(Heroicon::OutlinedHandRaised)
                ->color($readyForExecutor > 0 ? 'warning' : 'gray')
                ->url(ItemRequestResource::getUrl(parameters: ['tableFilters' => ['awaiting_executor' => ['isActive' => true]]])),
            Stat::make('Sedang Diproses', Rupiah::number($processing))
                ->description('executor sedang menyiapkan barang')
                ->descriptionIcon(Heroicon::OutlinedArrowPath)
                ->color('info')
                ->url(self::statusUrl(ItemRequestClientStatus::Processed)),
            Stat::make('Menunggu Konfirmasi Pemohon', Rupiah::number($awaitingRequester))
                ->description('selesai oleh executor, belum dikonfirmasi')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($awaitingRequester > 0 ? 'primary' : 'gray')
                ->url(self::statusUrl(ItemRequestClientStatus::Closed)),
            Stat::make('Diterima Tahun Ini', Rupiah::number($accepted))
                ->description(Rupiah::percent($accepted, $thisYear).' dari pengajuan tahun ini')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->url(self::statusUrl(ItemRequestClientStatus::Accepted)),
            Stat::make('Dibatalkan Tahun Ini', Rupiah::number($cancelled))
                ->description(Rupiah::percent($cancelled, $thisYear).' dari pengajuan tahun ini')
                ->descriptionIcon(Heroicon::OutlinedXCircle)
                ->color($cancelled > 0 ? 'danger' : 'gray')
                ->url(self::statusUrl(ItemRequestClientStatus::Cancelled)),
            Stat::make('Biaya Pengajuan Tahun Ini', Rupiah::compact($cost))
                ->description($thisYear - $cancelled > 0 ? 'rata-rata '.Rupiah::compact($cost / max(1, $thisYear - $cancelled)).' per pengajuan' : 'belum ada pengajuan')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
        ];
    }

    protected function requests(): Builder
    {
        return ItemRequestResource::getEloquentQuery();
    }

    protected function waiting(): Builder
    {
        return $this->requests()->where('client_status', ItemRequestClientStatus::Waiting->value);
    }

    /**
     * Menunggu dan sudah lewat tahap Accounting (atau tipenya tidak mensyaratkannya).
     */
    protected function pastAccounting(): Builder
    {
        return $this->waiting()->where(fn (Builder $ready) => $ready
            ->whereHas('type', fn (Builder $type) => $type->where('requires_accounting_approval', false))
            ->orWhereHas('approvals', fn (Builder $approvals) => $approvals
                ->where('approval_type', ItemRequestApprovalType::Accounting->value)
                ->whereNotNull('approved_by_user_id')));
    }

    protected static function statusUrl(ItemRequestClientStatus $status): string
    {
        return ItemRequestResource::getUrl(parameters: ['tableFilters' => ['client_status' => ['value' => $status->value]]]);
    }
}
