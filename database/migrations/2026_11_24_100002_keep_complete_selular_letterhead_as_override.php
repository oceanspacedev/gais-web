<?php

use App\Support\StoredFile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Dulu setiap badan usaha tanpa kop jatuh ke public/images/cvcs_kop.png. Fallback
 * itu dihapus; supaya dokumen CV COMPLETE SELULAR sendiri tidak berubah, gambar
 * yang sama dipasang sebagai kop gambar (override) miliknya.
 */
return new class extends Migration
{
    private const PATH = 'kopsurat/cvcs_kop.png';

    private const NAME = 'CV COMPLETE SELULAR';

    public function up(): void
    {
        $entity = DB::table('business_entities')
            ->where('name', self::NAME)
            ->whereNull('letterhead')
            ->first();

        $source = public_path('images/cvcs_kop.png');

        if ($entity === null || ! is_file($source)) {
            return;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists(self::PATH)) {
            $disk->put(self::PATH, file_get_contents($source), StoredFile::uploadVisibility());
        }

        DB::table('business_entities')->where('id', $entity->id)->update(['letterhead' => self::PATH]);
    }

    public function down(): void
    {
        DB::table('business_entities')
            ->where('name', self::NAME)
            ->where('letterhead', self::PATH)
            ->update(['letterhead' => null]);
    }
};
