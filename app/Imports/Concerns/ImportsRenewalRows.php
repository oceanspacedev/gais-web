<?php

namespace App\Imports\Concerns;

use EightyNine\ExcelImport\Exceptions\ImportStoppedException;
use Illuminate\Support\Collection;

/**
 * Aturan bersama impor baris pembaharuan/perpanjangan satu kontrak (sumo:
 * importUpdate): baris harus menyebut kode kontrak induk yang sedang dibuka,
 * sel kosong tidak mengubah baris yang sudah ada, dan tanggal hanya wajib untuk
 * baris baru. Kesalahan dilempar sebagai ImportStoppedException supaya
 * ExcelImportAction menampilkannya sebagai notifikasi dan membatalkan impor.
 */
trait ImportsRenewalRows
{
    use ParsesLegacyCells;

    /**
     * Nilai sel yang sudah diurai, atau nilai lama bila selnya kosong.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     * @param  callable(mixed): mixed  $parse
     */
    protected function cell(Collection|array $row, string $column, callable $parse, mixed $current = null): mixed
    {
        $raw = $row[$column] ?? null;

        return $this->text($raw) === null ? $current : $parse($raw);
    }

    /**
     * Tanggal dari sel: wajib untuk baris baru, dibiarkan bila kosong pada baris
     * lama, ditolak bila terisi tetapi tidak terbaca.
     *
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     */
    protected function dateCell(Collection|array $row, string $column, int $rowNumber, bool $required, mixed $current = null): mixed
    {
        $raw = $row[$column] ?? null;

        if ($this->text($raw) === null) {
            if ($required) {
                $this->reject($rowNumber, "kolom {$column} wajib diisi untuk baris baru.");
            }

            return $current;
        }

        return $this->date($raw) ?? $this->reject($rowNumber, "kolom {$column} tidak terbaca sebagai tanggal (pakai d/m/Y atau Y-m-d).");
    }

    /**
     * @param  Collection<string, mixed>|array<string, mixed>  $row
     */
    protected function isBlankRow(Collection|array $row): bool
    {
        foreach ($row as $value) {
            if ($this->text($value) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Kode induk di baris sama dengan kode kontrak yang dibuka (abaikan huruf besar-kecil).
     */
    protected function matchesParent(mixed $cell, ?string $parentCode): bool
    {
        $code = $this->text($cell);

        return $code !== null && strcasecmp($code, trim((string) $parentCode)) === 0;
    }

    /**
     * Nomor baris di Excel: indeks koleksi mulai dari 0 setelah baris judul.
     */
    protected function rowNumber(int $index): int
    {
        return $index + 2;
    }

    protected function reject(int $rowNumber, string $message): never
    {
        throw new ImportStoppedException("Baris {$rowNumber}: {$message}");
    }
}
