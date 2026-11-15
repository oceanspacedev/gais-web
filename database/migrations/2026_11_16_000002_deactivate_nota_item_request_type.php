<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipe pengajuan NOTA tidak boleh dipakai lagi. Tipenya cukup dinonaktifkan:
 * tidak muncul saat membuat pengajuan baru, sedangkan pengajuan NOTA lama
 * tetap tersimpan, terlihat, dan bisa diselesaikan seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('item_request_types')
            ->whereRaw('UPPER(name) = ?', ['NOTA'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('item_request_types')
            ->whereRaw('UPPER(name) = ?', ['NOTA'])
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
