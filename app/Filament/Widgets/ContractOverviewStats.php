<?php

namespace App\Filament\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Resources\InsuranceResource;
use App\Filament\Resources\RentResource;
use App\Filament\Widgets\Concerns\GuardsWidgetWithPermission;
use App\Models\Insurance;
use App\Models\Rent;
use App\Support\ContractDue;
use App\Support\Dashboard\Rupiah;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Dasbor > Asuransi & Sewa: polis dan kontrak yang berjalan, nilai
 * pertanggungan, premi ke depan, nilai sewa, tagihan, dan yang mendekati jatuh
 * tempo. Nilai memakai baris yang berlaku (pembaharuan terakhir bila ada).
 */
class ContractOverviewStats extends StatsOverviewWidget
{
    use GuardsWidgetWithPermission;

    protected ?string $heading = 'Ringkasan Asuransi & Sewa';

    protected ?string $description = 'Polis dan kontrak yang masih berjalan, memakai nilai pembaharuan terakhir bila ada.';

    protected ?string $pollingInterval = null;

    protected static function hasAnythingToShow(): bool
    {
        return InsuranceResource::canViewAny() || RentResource::canViewAny();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        return [
            ...(InsuranceResource::canViewAny() ? $this->insuranceStats() : []),
            ...(RentResource::canViewAny() ? $this->rentStats() : []),
        ];
    }

    /**
     * @return list<Stat>
     */
    protected function insuranceStats(): array
    {
        $active = InsuranceResource::getEloquentQuery()->activeContracts()->with('updates')->get();

        $stockWorth = (float) $active->sum(fn (Insurance $insurance): int => (int) $insurance->effective('stock_worth'));
        $buildingWorth = (float) $active->sum(fn (Insurance $insurance): int => (int) $insurance->effective('building_worth'));
        $dueSoon = $active->filter(fn (Insurance $insurance): bool => ($expiry = $insurance->effectiveExpiredDate()) !== null && ContractDue::daysUntil($expiry) <= 30);
        $overdue = $dueSoon->filter(fn (Insurance $insurance): bool => ContractDue::daysUntil($insurance->effectiveExpiredDate()) < 0)->count();

        $excluded = [...ContractStatus::endedValues(), ContractStatus::Renewed->value];
        $ids = InsuranceResource::getEloquentQuery()->select('insurances.id');
        $window = [today()->toDateString(), today()->addMonths(12)->toDateString()];
        $premium = (float) DB::table('insurances')->whereIn('id', $ids)->whereNull('deleted_at')->whereNotIn('status', $excluded)->whereBetween('expired_date', $window)->sum(DB::raw('COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)'))
            + (float) DB::table('insurance_updates')->whereIn('insurance_id', $ids)->whereNull('deleted_at')->whereNotIn('status', $excluded)->whereBetween('expired_date', $window)->sum(DB::raw('COALESCE(stock_premium, 0) + COALESCE(building_premium, 0)'));
        $periods = (int) DB::table('insurances')->whereIn('id', $ids)->whereNull('deleted_at')->whereNotIn('status', $excluded)->whereBetween('expired_date', $window)->count()
            + (int) DB::table('insurance_updates')->whereIn('insurance_id', $ids)->whereNull('deleted_at')->whereNotIn('status', $excluded)->whereBetween('expired_date', $window)->count();

        return [
            Stat::make('Polis Asuransi Aktif', Rupiah::number($active->count()))
                ->description(Rupiah::number($dueSoon->count()).' jatuh tempo ≤ 30 hari')
                ->descriptionIcon(Heroicon::OutlinedShieldCheck)
                ->color('success')
                ->url(InsuranceResource::getUrl()),
            Stat::make('Nilai Pertanggungan', Rupiah::compact($stockWorth + $buildingWorth))
                ->description('stok '.Rupiah::compact($stockWorth).' · bangunan '.Rupiah::compact($buildingWorth))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Premi 12 Bulan ke Depan', Rupiah::compact($premium))
                ->description(Rupiah::number($periods).' periode polis berakhir dan perlu diperbaharui')
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->color('info'),
            Stat::make('Polis Jatuh Tempo ≤ 30 Hari', Rupiah::number($dueSoon->count()))
                ->description($overdue > 0 ? Rupiah::number($overdue).' sudah lewat jatuh tempo' : 'belum ada yang lewat jatuh tempo')
                ->descriptionIcon(Heroicon::OutlinedBellAlert)
                ->color($dueSoon->isNotEmpty() ? 'danger' : 'gray')
                ->url(InsuranceResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]])),
        ];
    }

    /**
     * @return list<Stat>
     */
    protected function rentStats(): array
    {
        $active = RentResource::getEloquentQuery()->activeContracts()->with('updates')->get();

        $perYear = (float) $active->sum(fn (Rent $rent): int => (int) $rent->effective('rent_per_year'));
        $billing = $active->map(fn (Rent $rent): array => $rent->effectiveBillingSummary());
        $unpaid = $billing->filter(fn (array $summary): bool => $summary['remaining'] > 0);
        $inReminder = $active->filter(fn (Rent $rent): bool => ($reminder = $rent->effectiveReminderDate()) !== null && ContractDue::daysUntil($reminder) <= 0);
        $expired = $active->filter(fn (Rent $rent): bool => ($expiry = $rent->effectiveExpiredDate()) !== null && ContractDue::daysUntil($expiry) < 0)->count();

        return [
            Stat::make('Sewa Aktif', Rupiah::number($active->count()))
                ->description(Rupiah::number($active->filter(fn (Rent $rent): bool => ($expiry = $rent->effectiveExpiredDate()) !== null && ContractDue::daysUntil($expiry) <= 30)->count()).' berakhir ≤ 30 hari')
                ->descriptionIcon(Heroicon::OutlinedBuildingStorefront)
                ->color('success')
                ->url(RentResource::getUrl()),
            Stat::make('Nilai Sewa per Tahun', Rupiah::compact($perYear))
                ->description('total kontrak berjalan · rata-rata '.Rupiah::compact($active->count() > 0 ? $perYear / $active->count() : 0))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('primary'),
            Stat::make('Tagihan Sewa Belum Lunas', Rupiah::number($unpaid->count()))
                ->description('sisa '.Rupiah::compact((float) $unpaid->sum('remaining')).' dari '.Rupiah::compact((float) $billing->sum('total')))
                ->descriptionIcon(Heroicon::OutlinedReceiptPercent)
                ->color($unpaid->isNotEmpty() ? 'warning' : 'gray')
                ->url(RentResource::getUrl()),
            Stat::make('Sewa Masa Pengingat', Rupiah::number($inReminder->count()))
                ->description($expired > 0 ? Rupiah::number($expired).' sudah lewat tanggal berakhir' : 'sudah masuk tanggal pengingat (bulan sebelum berakhir)')
                ->descriptionIcon(Heroicon::OutlinedBellAlert)
                ->color($inReminder->isNotEmpty() ? 'danger' : 'gray')
                ->url(RentResource::getUrl(parameters: ['tableFilters' => ['due_soon' => ['isActive' => true]]])),
        ];
    }
}
