<?php

namespace App\Models\Concerns;

use App\Support\ContractDue;

/**
 * Selisih dan status nilai stok satu baris polis (induk atau pembaharuan), sama
 * dengan kolom "Selisih" dan "Status Stok" di daftar asuransi sumo.
 */
trait ComputesInsuranceStock
{
    public function stockDifference(): ?int
    {
        if ($this->stock_worth === null && $this->actual_stock_worth === null) {
            return null;
        }

        return (int) $this->stock_worth - (int) $this->actual_stock_worth;
    }

    /**
     * Overlimit bila nilai aktual melebihi nilai stok 30% atau lebih, Underlimit
     * bila di bawahnya 30% atau lebih, selain itu OK.
     */
    public function stockStatus(): ?string
    {
        if ($this->stock_worth === null && $this->actual_stock_worth === null) {
            return null;
        }

        $stock = (int) $this->stock_worth;
        $actual = (int) $this->actual_stock_worth;
        $threshold = $stock * 0.3;

        if ($actual > $stock) {
            return ($actual - $stock) >= $threshold ? 'Overlimit' : 'OK';
        }

        return ($stock - $actual) >= $threshold ? 'Underlimit' : 'OK';
    }

    public static function stockStatusColor(?string $status): string
    {
        return match ($status) {
            'OK' => 'success',
            'Overlimit', 'Underlimit' => 'warning',
            default => 'gray',
        };
    }

    public function dueColor(): ?string
    {
        return ContractDue::insuranceColor($this->expired_date, $this->status);
    }
}
