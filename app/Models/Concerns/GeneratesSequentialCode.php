<?php

namespace App\Models\Concerns;

/**
 * Kode berurutan ala sumo: PREFIX + nomor urut empat digit dari jumlah baris
 * (termasuk yang terhapus), dilewati bila kodenya sudah dipakai. Model pemakai
 * mendefinisikan CODE_PREFIX dan kolom kodenya `rent_code`.
 */
trait GeneratesSequentialCode
{
    protected static function bootGeneratesSequentialCode(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->rent_code)) {
                $model->rent_code = static::nextCode();
            }
        });
    }

    public static function nextCode(): string
    {
        $number = static::withTrashed()->count();

        do {
            $code = static::CODE_PREFIX.str_pad((string) (++$number), 4, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('rent_code', $code)->exists());

        return $code;
    }
}
