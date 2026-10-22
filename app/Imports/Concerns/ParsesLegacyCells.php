<?php

namespace App\Imports\Concerns;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Pembacaan sel Excel ala sumo: teks kosong jadi null, angka boleh memakai
 * pemisah ribuan, tanggal boleh serial Excel atau teks d/m/Y.
 */
trait ParsesLegacyCells
{
    protected function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function money(mixed $value): ?int
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9\-]/', '', str_replace(['.', ','], ['', '.'], $value));

        return $digits === '' || $digits === '-' ? null : (int) $digits;
    }

    protected function number(mixed $value): ?float
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        $normalized = str_replace(',', '.', preg_replace('/[^0-9,.\-]/', '', $value) ?? '');

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    protected function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value > 1000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            }

            $value = trim((string) $value);

            foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->toDateString();
                }
            }

            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    protected function status(mixed $value, string $default = 'BERJALAN'): string
    {
        $value = strtoupper($this->text($value) ?? '');

        return in_array($value, ['BERJALAN', 'PEMBAHARUAN', 'TUTUP', 'REFUND'], true) ? $value : $default;
    }
}
