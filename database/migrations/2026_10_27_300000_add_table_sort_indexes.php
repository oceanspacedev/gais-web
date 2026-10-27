<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk urutan bawaan tabel admin (created_at desc, expired_date, nama)
 * dan opsi filter yang diurutkan per nama (pemohon, penerima, PIC, plat). Tanpa index
 * MySQL membaca dan mengurutkan seluruh tabel hanya untuk 10 baris pertama.
 */
return new class extends Migration
{
    /** @var array<string, list<list<string>>> */
    private const INDEXES = [
        'users' => [['name'], ['created_at']],
        'assets' => [['name']],
        'asset_transfers' => [['created_at']],
        'asset_services' => [['created_at']],
        'item_request_logs' => [['created_at']],
        // Opsi filter plat/PIC/lokasi dihitung (DISTINCT) di setiap pemuatan halaman.
        'vehicle_checksheets' => [['created_at'], ['license_plate', 'deleted_at'], ['pic', 'deleted_at'], ['location', 'deleted_at']],
        'insurances' => [['expired_date']],
        'rents' => [['expired_date']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (! Schema::hasIndex($table, $columns)) {
                        $blueprint->index($columns);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $indexes) {
                foreach ($indexes as $columns) {
                    if (Schema::hasIndex($table, $columns)) {
                        $blueprint->dropIndex($columns);
                    }
                }
            });
        }
    }
};
