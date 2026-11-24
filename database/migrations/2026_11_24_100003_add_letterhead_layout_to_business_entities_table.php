<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posisi teks kop di samping logo (kanan/tengah/kiri). Null = kanan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_entities', function (Blueprint $table) {
            $table->string('letterhead_layout', 16)->nullable()->after('logo');
        });
    }

    public function down(): void
    {
        Schema::table('business_entities', function (Blueprint $table) {
            $table->dropColumn('letterhead_layout');
        });
    }
};
