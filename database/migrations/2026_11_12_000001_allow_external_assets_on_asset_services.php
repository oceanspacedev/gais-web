<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Servis boleh mencatat aset yang tidak terdaftar di daftar aset: asset_id
 * jadi opsional dan identitas aset luar disimpan langsung di baris servis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_services', function (Blueprint $table) {
            $table->unsignedBigInteger('asset_id')->nullable()->change();
            $table->string('external_asset_name')->nullable()->after('asset_id');
            $table->string('external_asset_serial_number', 150)->nullable()->after('external_asset_name');
        });
    }

    public function down(): void
    {
        // Servis aset luar tidak punya asset_id, jadi tidak bisa dikembalikan ke kolom wajib.
        DB::table('asset_services')->whereNull('asset_id')->delete();

        Schema::table('asset_services', function (Blueprint $table) {
            $table->dropColumn(['external_asset_name', 'external_asset_serial_number']);
            $table->unsignedBigInteger('asset_id')->nullable(false)->change();
        });
    }
};
