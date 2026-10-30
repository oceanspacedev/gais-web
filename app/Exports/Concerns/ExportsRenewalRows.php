<?php

namespace App\Exports\Concerns;

/**
 * Ekspor baris pembaharuan/perpanjangan memakai kolom template impor, supaya
 * file hasil ekspor bisa diimpor kembali tanpa diubah (sumo: exportUpdate).
 */
trait ExportsRenewalRows
{
    /**
     * Susun nilai berkunci kolom mengikuti urutan judul; kolom tanpa nilai kosong.
     *
     * @param  array<string, mixed>  $values
     * @return list<mixed>
     */
    protected function orderedByHeadings(array $values): array
    {
        return array_map(fn (string $heading): mixed => $values[$heading] ?? null, $this->headings());
    }

    /**
     * Nama file yang aman: selain huruf, angka, titik, strip, dan garis bawah diganti strip.
     */
    protected static function safeFileName(string $prefix, ?string $code, string $fallback): string
    {
        $code = trim((string) $code);
        $safe = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $code === '' ? $fallback : $code), '-');

        return $prefix.'-'.($safe === '' ? $fallback : $safe).'.xlsx';
    }
}
