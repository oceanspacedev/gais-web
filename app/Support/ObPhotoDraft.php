<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Draf foto OB yang belum disimpan, per user. Foto sudah terunggah begitu
 * diambil, tetapi path-nya hanya ada di halaman; di HP halaman sering termuat
 * ulang saat kamera dibuka, sehingga semua foto di form ikut hilang. Draf ini
 * mengembalikannya ketika form dibuka lagi.
 */
final class ObPhotoDraft
{
    public const CREATE = 'create';

    public const BEFORE = 'before';

    public const AFTER = 'after';

    private const HOURS = 12;

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public static function get(string $bucket): array
    {
        $draft = Cache::get(self::key($bucket));

        return is_array($draft) ? $draft : [];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $draft
     */
    public static function put(string $bucket, array $draft): void
    {
        if ($draft === []) {
            self::forget($bucket);

            return;
        }

        Cache::put(self::key($bucket), $draft, now()->addHours(self::HOURS));
    }

    public static function forget(string $bucket): void
    {
        Cache::forget(self::key($bucket));
    }

    private static function key(string $bucket): string
    {
        return 'ob-photo-draft:'.auth()->id().':'.$bucket;
    }
}
