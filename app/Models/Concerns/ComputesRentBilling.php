<?php

namespace App\Models\Concerns;

use App\Support\ContractDue;
use Carbon\CarbonInterface;

/**
 * Perhitungan tagihan satu baris kontrak sewa (induk atau perpanjangan), sama
 * dengan daftar dan detail sewa di sumo.
 */
trait ComputesRentBilling
{
    /**
     * Jumlah tahun = hari sewa dibagi 366 dibulatkan ke atas, total = tahun x sewa
     * per tahun, dibayar = dana PKP + dana NON-PKP. Status LUNAS bila sisa 0, LEBIH
     * bila pembayaran melebihi, BELUM LUNAS bila masih ada sisa.
     *
     * @return array{years: int, total: int, paid: int, remaining: int, status: string}
     */
    public function billingSummary(): array
    {
        $days = $this->join_date !== null && $this->expired_date !== null
            ? abs((int) $this->join_date->copy()->startOfDay()->diffInDays($this->expired_date->copy()->startOfDay(), false))
            : 0;
        $years = (int) ceil($days / 366);
        $total = $years * (int) ($this->rent_per_year ?? 0);
        $paid = (int) ($this->cvcs_fund ?? 0) + (int) ($this->online_fund ?? 0);
        $remaining = $total - $paid;

        return [
            'years' => $years,
            'total' => $total,
            'paid' => $paid,
            'remaining' => $remaining,
            'status' => $remaining < 0 ? 'LEBIH' : ($remaining === 0 ? 'LUNAS' : 'BELUM LUNAS'),
        ];
    }

    public static function billingColor(string $status): string
    {
        return match ($status) {
            'LUNAS' => 'success',
            'LEBIH' => 'info',
            default => 'warning',
        };
    }

    public function reminderDate(): ?CarbonInterface
    {
        return ContractDue::reminderDate($this->expired_date, $this->month_before_reminder);
    }

    public function dueColor(): ?string
    {
        return ContractDue::rentColor($this->expired_date, $this->month_before_reminder, $this->status);
    }
}
