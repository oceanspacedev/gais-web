<?php

namespace App\Support;

/**
 * File unggahan sumo tersimpan sebagai nama file saja di folder tetap pada disk
 * `public`; unggahan baru dari Filament menyimpan path lengkapnya. Helper ini
 * menyamakan keduanya.
 */
final class LegacyUpload
{
    public const REQUEST_DIR = 'Request_File';

    public const PROBLEM_REPORT_DIR = 'Problem_Report_File';

    public const RENT_DIR = 'Rent_File';

    public const PRODUCT_DIR = 'product';

    public const PROFILE_DIR = 'profile';

    public static function path(string $directory, ?string $file): ?string
    {
        $file = trim((string) $file);

        if ($file === '') {
            return null;
        }

        return str_contains($file, '/') ? $file : trim($directory, '/').'/'.$file;
    }

    public static function url(string $directory, ?string $file): ?string
    {
        $path = self::path($directory, $file);

        return $path === null ? null : StoredFile::url($path);
    }

    public static function fileName(?string $file): ?string
    {
        $file = trim((string) $file);

        return $file === '' ? null : basename($file);
    }
}
