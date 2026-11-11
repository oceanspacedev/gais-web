<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft delete menjadi standar semua fitur: hapus dari panel hanya mengisi
 * deleted_at, record masuk filter Sampah dan bisa dipulihkan. Tabel modul sumo,
 * OB, Task, dan checksheet kendaraan sudah memilikinya; ini melengkapi sisanya.
 * Pengecualian yang disengaja: users (identitas login dan sinkron Talenta/sumo
 * memakai kolom unik), log label QR, rekonsiliasi dan impor sumo (alat impor).
 */
return new class extends Migration
{
    private const TABLES = [
        'assets',
        'asset_locations',
        'asset_services',
        'asset_transfers',
        'brands',
        'business_entities',
        'categories',
        'custom_asset_attributes',
        'item_request_settings',
        'job_titles',
        'vendors',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
