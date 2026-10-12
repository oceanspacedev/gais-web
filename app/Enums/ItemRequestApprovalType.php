<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tahap approval pengajuan barang, urut sesuai alur sumo.
 */
enum ItemRequestApprovalType: string implements HasLabel
{
    case Accounting = 'ACCOUNTING';
    case Manager = 'MANAGER';
    case Executor = 'EXECUTOR';
    case EndUser = 'ENDUSER';

    public function label(): string
    {
        return match ($this) {
            self::Accounting => 'Accounting',
            self::Manager => 'Manager',
            self::Executor => 'Executor',
            self::EndUser => 'Pemohon',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
