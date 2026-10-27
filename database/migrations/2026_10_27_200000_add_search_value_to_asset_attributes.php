<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nilai atribut yang sudah dinormalisasi (huruf kecil, tanpa spasi, tanda hubung,
 * titik, garis miring) untuk pencarian aset. Disimpan oleh database supaya
 * pencarian tidak menghitung REPLACE berlapis untuk setiap baris setiap kali;
 * di 100 ribu aset / 400 ribu atribut ini memangkas waktu pencarian sekitar separuh.
 */
return new class extends Migration
{
    public const EXPRESSION = "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(attribute_value, '')), ' ', ''), '-', ''), '.', ''), '/', ''))";

    public function up(): void
    {
        Schema::table('asset_attributes', function (Blueprint $table) {
            $table->text('search_value')->nullable()->storedAs(self::EXPRESSION);
        });
    }

    public function down(): void
    {
        Schema::table('asset_attributes', function (Blueprint $table) {
            $table->dropColumn('search_value');
        });
    }
};
