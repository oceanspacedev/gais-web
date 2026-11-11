<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Filter status pada Rekap per Divisi; angkanya sama dengan selectFilterRequest
 * di ekspor sumo (RequestExport). Semua pilihan kecuali Dibatalkan mengabaikan
 * pengajuan yang dibatalkan.
 */
enum ItemRequestRecapStatus: int implements HasLabel
{
    /** Baris approval Executor belum diisi. */
    case AwaitingExecutor = 0;

    /** Baris approval Executor sudah diisi (diproses, selesai, atau diterima). */
    case ProcessedByExecutor = 1;

    /** Semua status kecuali dibatalkan; ini perilaku default sumo. */
    case NotCancelled = 3;

    case Cancelled = 4;

    /** Status akhir Selesai (executor sudah menutup, pemohon belum konfirmasi). */
    case Closed = 5;

    public function label(): string
    {
        return match ($this) {
            self::AwaitingExecutor => 'Belum diproses executor',
            self::ProcessedByExecutor => 'Sudah diproses executor',
            self::NotCancelled => 'Semua kecuali dibatalkan',
            self::Cancelled => 'Dibatalkan',
            self::Closed => 'Selesai',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Nilai dari form bisa berupa enum, angka, atau string angka.
     */
    public static function coerce(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::from((int) $value);
    }
}
